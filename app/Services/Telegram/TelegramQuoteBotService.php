<?php

namespace App\Services\Telegram;

use App\Models\Quote;
use App\Models\User;
use App\Services\Quotes\QuoteAutomationService;
use App\Support\AppPermissions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

class TelegramQuoteBotService
{
    private const STATE_KEY_PREFIX = 'telegram.cotizaciones.chat_state.';

    private const SELECTION_PAGE_SIZE = 8;

    private const LIST_PAGE_SIZE = 10;

    private const ACTION_CREATE_GUIDED = 'create_guided';

    private const ACTION_LIST_BROWSE = 'list_browse';

    private const ACTION_LIST_SEARCH_INPUT = 'list_search_input';

    private const ACTION_ADD_PAYMENT_SELECT_QUOTE = 'add_payment_select_quote';

    private const ACTION_ADD_PAYMENT_COLLECT_DATA = 'add_payment_collect_data';

    private const ACTION_EDIT_SELECT_QUOTE = 'edit_select_quote';

    private const ACTION_EDIT_CHOOSE_FIELD = 'edit_choose_field';

    private const ACTION_EDIT_SET_VALUE = 'edit_set_value';

    private const ACTION_SEND_PDF_SELECT_QUOTE = 'send_pdf_select_quote';

    public function __construct(
        private readonly TelegramClient $telegramClient,
        private readonly QuoteAutomationService $quoteAutomationService,
        private readonly TelegramUserLinkService $telegramUserLinkService,
    ) {
    }

    /**
     * @param  array<string, mixed>  $update
     */
    public function processUpdate(array $update): void
    {
        $callbackQuery = data_get($update, 'callback_query');

        if (is_array($callbackQuery)) {
            $callbackId = trim((string) data_get($callbackQuery, 'id', ''));
            $chatId = data_get($callbackQuery, 'message.chat.id');
            $data = trim((string) data_get($callbackQuery, 'data', ''));

            if ($callbackId !== '') {
                $this->telegramClient->answerCallbackQuery($callbackId);
            }

            if ($chatId === null || $data === '') {
                return;
            }

            $this->handleIncomingText((string) $chatId, $data);

            return;
        }

        $message = data_get($update, 'message');

        if (!is_array($message)) {
            return;
        }

        $chatId = data_get($message, 'chat.id');
        $text = trim((string) data_get($message, 'text', ''));

        if ($chatId === null || $text === '') {
            return;
        }

        $this->handleIncomingText((string) $chatId, $text);
    }

    private function handleIncomingText(string $chatIdString, string $text): void
    {
        $text = trim($text);

        if ($text === '') {
            return;
        }

        if (!$this->isAllowedChat($chatIdString)) {
            $this->telegramClient->sendMessage($chatIdString, 'Este chat no está autorizado para usar el bot de cotizaciones.');

            return;
        }

        if ($this->isLinkCommand($text)) {
            $this->processLinkCommand($chatIdString, $text);

            return;
        }

        $linkedUser = $this->telegramUserLinkService->findLinkedUserByChatId($chatIdString);

        if ($linkedUser === null) {
            $this->clearState($chatIdString);
            $this->sendLinkRequiredMessage($chatIdString);

            return;
        }

        if ($this->isCancelCommand($text)) {
            $this->clearState($chatIdString);
            $this->sendMainMenu($chatIdString, 'Operación cancelada.');

            return;
        }

        if ($this->isHelpMessage($text)) {
            $this->clearState($chatIdString);
            $this->sendMainMenu($chatIdString, $this->helpMessage());

            return;
        }

        $state = $this->getState($chatIdString);

        if ($state !== null && $this->processStatefulMessage($chatIdString, $text, $state, $linkedUser)) {
            return;
        }

        // Priorizar opciones del menú por texto sobre la detección por lenguaje natural.
        if ($this->handleMainMenuOption($chatIdString, $text, $linkedUser)) {
            return;
        }

        if ($this->isListCommand($text)) {
            if (!$this->ensurePermission($chatIdString, $linkedUser, AppPermissions::QUOTES_VIEW)) {
                return;
            }

            $this->startQuoteListBrowse($chatIdString);

            return;
        }

        if ($this->isSendPdfCommand($text)) {
            if (!$this->ensurePermission($chatIdString, $linkedUser, AppPermissions::QUOTES_VIEW)) {
                return;
            }

            if ($this->tryDirectSendPdf($chatIdString, $text)) {
                return;
            }

            $this->startSendPdfFlow($chatIdString);

            return;
        }

        if ($this->isAddPaymentCommand($text)) {
            if (!$this->ensurePermission($chatIdString, $linkedUser, AppPermissions::QUOTES_EDIT)) {
                return;
            }

            if ($this->tryDirectAddPayment($chatIdString, $text)) {
                return;
            }

            $this->startAddPaymentFlow($chatIdString);

            return;
        }

        if ($this->isEditCommand($text)) {
            if (!$this->ensurePermission($chatIdString, $linkedUser, AppPermissions::QUOTES_EDIT)) {
                return;
            }

            $this->startEditQuoteFlow($chatIdString);

            return;
        }

        if ($this->looksLikeQuoteCreation($text)) {
            if (!$this->ensurePermission($chatIdString, $linkedUser, AppPermissions::QUOTES_CREATE)) {
                return;
            }

            $draft = app(\App\Services\Ai\QuoteInstructionParser::class)->parse($text);
            $quoteData = is_array($draft['quote'] ?? null) ? $draft['quote'] : [];

            if (($draft['can_create'] ?? false) === true) {
                try {
                    $quote = $this->quoteAutomationService->createFromStructuredData($quoteData);
                    $this->sendMessage(
                        $chatIdString,
                        "Cotización creada correctamente.\n".$this->buildQuoteSummary($quote, true),
                        $this->buildMainMenuKeyboard()
                    );
                    $this->sendQuotePdf($chatIdString, $quote, 'PDF de '.$this->pdfDisplayName($quote));

                    return;
                } catch (Throwable) {
                    // Si falla el guardado automático, migrar a guiado con el contexto detectado.
                }
            }

            $seedDraft = $this->buildGuidedSeedDraft($quoteData);
            $reason = trim((string) ($draft['reason'] ?? ''));

            if ($reason === '') {
                $reason = 'Puedo ayudarte mejor si completamos algunos datos en modo guiado.';
            }

            $this->startGuidedCreateFlow(
                $chatIdString,
                $seedDraft,
                $reason
            );

            return;
        }

        $this->sendMessage(
            $chatIdString,
            'Escribe una opción del menú para continuar.',
            $this->buildMainMenuKeyboard()
        );
    }

    /**
     * Detecta si el texto parece una solicitud de creación de cotización.
     */
    private function looksLikeQuoteCreation(string $text): bool
    {
        $text = mb_strtolower($text);
        return (
            str_contains($text, 'cotiz') ||
            str_contains($text, 'presupuest') ||
            (str_contains($text, 'cliente') && (str_contains($text, 'concepto') || str_contains($text, 'partida')))
        );
    }

    /**
     * @param  array<string, mixed>  $state
     */
    private function processStatefulMessage(string $chatId, string $text, array $state, User $linkedUser): bool
    {
        $action = (string) ($state['action'] ?? '');

        $requiredPermission = match ($action) {
            self::ACTION_CREATE_GUIDED => AppPermissions::QUOTES_CREATE,
            self::ACTION_LIST_BROWSE, self::ACTION_LIST_SEARCH_INPUT, self::ACTION_SEND_PDF_SELECT_QUOTE => AppPermissions::QUOTES_VIEW,
            self::ACTION_ADD_PAYMENT_SELECT_QUOTE, self::ACTION_ADD_PAYMENT_COLLECT_DATA, self::ACTION_EDIT_SELECT_QUOTE, self::ACTION_EDIT_CHOOSE_FIELD, self::ACTION_EDIT_SET_VALUE => AppPermissions::QUOTES_EDIT,
            default => null,
        };

        if ($requiredPermission !== null && !$this->ensurePermission($chatId, $linkedUser, $requiredPermission, true)) {
            $this->clearState($chatId);

            return true;
        }

        return match ($action) {
            self::ACTION_CREATE_GUIDED => $this->handleGuidedCreateMessage($chatId, $text, $state),
            self::ACTION_LIST_BROWSE => $this->handleListBrowseMessage($chatId, $text, $state),
            self::ACTION_LIST_SEARCH_INPUT => $this->handleListSearchInputMessage($chatId, $text),
            self::ACTION_ADD_PAYMENT_SELECT_QUOTE => $this->handleAddPaymentSelectQuote($chatId, $text, $state),
            self::ACTION_ADD_PAYMENT_COLLECT_DATA => $this->handleAddPaymentCollectData($chatId, $text, $state),
            self::ACTION_EDIT_SELECT_QUOTE => $this->handleEditSelectQuote($chatId, $text, $state),
            self::ACTION_EDIT_CHOOSE_FIELD => $this->handleEditChooseField($chatId, $text, $state),
            self::ACTION_EDIT_SET_VALUE => $this->handleEditSetValue($chatId, $text, $state),
            self::ACTION_SEND_PDF_SELECT_QUOTE => $this->handleSendPdfSelectQuote($chatId, $text, $state),
            default => false,
        };
    }

    /**
     * @param  array<string, mixed>|null  $seedDraft
     */
    private function startGuidedCreateFlow(string $chatId, ?array $seedDraft = null, ?string $contextMessage = null): void
    {
        $draft = [
            'reference_code' => '',
            'client_name' => '',
            'client_rfc' => '',
            'location' => '',
            'issued_at' => now()->toDateString(),
            'terms' => '',
            'contact_name' => '',
            'contact_email' => '',
            'contact_phone' => '',
            'items' => [],
        ];

        if (is_array($seedDraft)) {
            $draft = array_merge($draft, [
                'reference_code' => trim((string) ($seedDraft['reference_code'] ?? '')),
                'client_name' => trim((string) ($seedDraft['client_name'] ?? '')),
                'client_rfc' => trim((string) ($seedDraft['client_rfc'] ?? '')),
                'location' => trim((string) ($seedDraft['location'] ?? '')),
                'issued_at' => trim((string) ($seedDraft['issued_at'] ?? '')) ?: now()->toDateString(),
                'terms' => trim((string) ($seedDraft['terms'] ?? '')),
                'contact_name' => trim((string) ($seedDraft['contact_name'] ?? '')),
                'contact_email' => trim((string) ($seedDraft['contact_email'] ?? '')),
                'contact_phone' => trim((string) ($seedDraft['contact_phone'] ?? '')),
            ]);
        }

        $startStep = $draft['client_name'] === '' ? 'client_name' : 'item_description';

        $this->setState($chatId, [
            'action' => self::ACTION_CREATE_GUIDED,
            'step' => $startStep,
            'draft' => $draft,
            'current_item' => [],
        ]);

        $lines = [];

        if ($contextMessage !== null && trim($contextMessage) !== '') {
            $lines[] = trim($contextMessage);
            $lines[] = '';
        }

        $lines[] = 'Creación guiada de cotización.';

        if ($startStep === 'client_name') {
            $lines[] = 'Paso 1 de 8: escribe el nombre del cliente.';

            $this->sendMessage(
                $chatId,
                implode("\n", $lines),
                $this->buildGuidedCancelKeyboard()
            );

            return;
        }

        $lines[] = 'Detecté datos iniciales. Continuemos con el concepto principal.';
        $lines[] = 'Paso 4 de 8: escribe la descripción del concepto.';

        $this->sendMessage(
            $chatId,
            implode("\n", $lines),
            $this->buildGuidedCancelKeyboard()
        );
    }

    /**
     * @param  array<string, mixed>  $quoteData
     * @return array<string, mixed>
     */
    private function buildGuidedSeedDraft(array $quoteData): array
    {
        return [
            'reference_code' => trim((string) ($quoteData['reference_code'] ?? '')),
            'client_name' => trim((string) ($quoteData['client_name'] ?? '')),
            'client_rfc' => trim((string) ($quoteData['client_rfc'] ?? '')),
            'location' => trim((string) ($quoteData['location'] ?? '')),
            'issued_at' => trim((string) ($quoteData['issued_at'] ?? '')),
            'terms' => trim((string) ($quoteData['terms'] ?? '')),
            'contact_name' => trim((string) ($quoteData['contact_name'] ?? '')),
            'contact_email' => trim((string) ($quoteData['contact_email'] ?? '')),
            'contact_phone' => trim((string) ($quoteData['contact_phone'] ?? '')),
        ];
    }

    private function startQuoteListBrowse(string $chatId, int $page = 1, ?string $search = null): void
    {
        $list = $this->buildQuoteSelectionData($page, self::LIST_PAGE_SIZE, $search);

        $this->setState($chatId, [
            'action' => self::ACTION_LIST_BROWSE,
            'page' => $list['page'],
            'search' => $search,
        ]);

        $this->sendMessage(
            $chatId,
            $list['text']."\n\n".
            "Para reenviar PDF, agregar anticipo o editar, usa el menú de acciones.",
            $this->buildListActionsKeyboard($list['has_prev'], $list['has_next'], $search !== null && $search !== '')
        );
    }

    private function startAddPaymentFlow(string $chatId): void
    {
        $selection = $this->buildQuoteSelectionData();

        if ($selection['options'] === []) {
            $this->sendMainMenu($chatId, $selection['text']);

            return;
        }

        $this->setState($chatId, [
            'action' => self::ACTION_ADD_PAYMENT_SELECT_QUOTE,
            'quote_options' => $selection['options'],
            'page' => $selection['page'],
        ]);

        $this->sendMessage(
            $chatId,
            "Selecciona la cotización a la que deseas registrar anticipo.\n".
            "Puedes responder con número, folio, id o número de proyecto.\n\n".
            $selection['text'],
            $this->buildQuoteSelectionKeyboard($selection['options'], $selection['has_prev'], $selection['has_next'])
        );
    }

    private function startEditQuoteFlow(string $chatId): void
    {
        $selection = $this->buildQuoteSelectionData();

        if ($selection['options'] === []) {
            $this->sendMainMenu($chatId, $selection['text']);

            return;
        }

        $this->setState($chatId, [
            'action' => self::ACTION_EDIT_SELECT_QUOTE,
            'quote_options' => $selection['options'],
            'page' => $selection['page'],
        ]);

        $this->sendMessage(
            $chatId,
            "Selecciona la cotización que deseas editar.\n".
            "Puedes responder con número, folio, id o número de proyecto.\n\n".
            $selection['text'],
            $this->buildQuoteSelectionKeyboard($selection['options'], $selection['has_prev'], $selection['has_next'])
        );
    }

    private function startSendPdfFlow(string $chatId): void
    {
        $selection = $this->buildQuoteSelectionData();

        if ($selection['options'] === []) {
            $this->sendMainMenu($chatId, $selection['text']);

            return;
        }

        $this->setState($chatId, [
            'action' => self::ACTION_SEND_PDF_SELECT_QUOTE,
            'quote_options' => $selection['options'],
            'page' => $selection['page'],
        ]);

        $this->sendMessage(
            $chatId,
            "Selecciona la cotización de la cual deseas reenviar el PDF.\n".
            "Puedes responder con número, folio, id o número de proyecto.\n\n".
            $selection['text'],
            $this->buildQuoteSelectionKeyboard($selection['options'], $selection['has_prev'], $selection['has_next'])
        );
    }

    private function sendQuoteList(string $chatId): void
    {
        $this->startQuoteListBrowse($chatId);
    }

    /**
     * @return array{text:string,options:array<string,int>,page:int,last_page:int,has_prev:bool,has_next:bool}
     */
    private function buildQuoteSelectionData(int $page = 1, int $perPage = self::SELECTION_PAGE_SIZE, ?string $search = null): array
    {
        $query = Quote::query();

        if ($search !== null && trim($search) !== '') {
            $term = trim($search);
            $query->where(function ($builder) use ($term): void {
                $builder
                    ->where('folio', 'like', '%'.$term.'%')
                    ->orWhere('reference_code', 'like', '%'.$term.'%')
                    ->orWhere('client_name', 'like', '%'.$term.'%');
            });
        }

        $total = (clone $query)->count();
        $lastPage = max(1, (int) ceil($total / max(1, $perPage)));
        $safePage = max(1, min($page, $lastPage));

        $quotes = $query
            ->latest('issued_at')
            ->latest('id')
            ->forPage($safePage, $perPage)
            ->get(['id', 'folio', 'reference_code', 'client_name', 'total']);

        if ($quotes->isEmpty()) {
            return [
                'text' => $search === null || trim($search) === ''
                    ? 'No hay cotizaciones registradas todavía.'
                    : 'No encontré cotizaciones con ese criterio de búsqueda.',
                'options' => [],
                'page' => 1,
                'last_page' => 1,
                'has_prev' => false,
                'has_next' => false,
            ];
        }

        $lines = [
            'Listado de cotizaciones (página '.$safePage.' de '.$lastPage.'): ',
        ];

        if ($search !== null && trim($search) !== '') {
            $lines[] = 'Filtro activo: '.$search;
        }

        $options = [];

        foreach ($quotes as $index => $quote) {
            $position = (string) ($index + 1);
            $options[$position] = (int) $quote->id;

            $lines[] =
                $position.') '.
                $quote->folio.
                ' | Cliente: '.$quote->client_name.
                ' | Proyecto: '.($quote->reference_code ?: 'Sin referencia').
                ' | Total: $'.number_format((float) $quote->total, 2).' + IVA';
        }

        return [
            'text' => implode("\n", $lines),
            'options' => $options,
            'page' => $safePage,
            'last_page' => $lastPage,
            'has_prev' => $safePage > 1,
            'has_next' => $safePage < $lastPage,
        ];
    }

    /**
     * @param  array<string, mixed>  $state
     */
    private function handleGuidedCreateMessage(string $chatId, string $text, array $state): bool
    {
        $step = (string) ($state['step'] ?? 'client_name');
        $draft = is_array($state['draft'] ?? null) ? $state['draft'] : [];
        $currentItem = is_array($state['current_item'] ?? null) ? $state['current_item'] : [];
        $trimmed = trim($text);

        if ($step === 'client_name') {
            if ($trimmed === '') {
                $this->sendMessage($chatId, 'El cliente es obligatorio. Escribe el nombre del cliente.', $this->buildGuidedCancelKeyboard());

                return true;
            }

            $draft['client_name'] = $trimmed;
            $this->setGuidedState($chatId, 'reference_code', $draft, $currentItem);
            $this->sendMessage($chatId, 'Paso 2 de 8: escribe el número de proyecto o pedido. Puedes omitirlo.', $this->buildGuidedOptionalKeyboard());

            return true;
        }

        if ($step === 'reference_code') {
            $draft['reference_code'] = $this->isSkipCommand($trimmed) ? '' : $trimmed;
            $this->setGuidedState($chatId, 'location', $draft, $currentItem);
            $this->sendMessage($chatId, 'Paso 3 de 8: escribe la ubicación del proyecto. Puedes omitirla.', $this->buildGuidedOptionalKeyboard());

            return true;
        }

        if ($step === 'location') {
            $draft['location'] = $this->isSkipCommand($trimmed) ? '' : $trimmed;
            $this->setGuidedState($chatId, 'item_description', $draft, []);
            $this->sendMessage($chatId, 'Paso 4 de 8: escribe la descripción del concepto.', $this->buildGuidedCancelKeyboard());

            return true;
        }

        if ($step === 'item_description') {
            if ($trimmed === '') {
                $this->sendMessage($chatId, 'La descripción es obligatoria. Captúrala para continuar.', $this->buildGuidedCancelKeyboard());

                return true;
            }

            $currentItem['description'] = $trimmed;
            $this->setGuidedState($chatId, 'item_quantity', $draft, $currentItem);
            $this->sendMessage($chatId, 'Paso 5 de 8: escribe la cantidad del concepto.', $this->buildGuidedCancelKeyboard());

            return true;
        }

        if ($step === 'item_quantity') {
            $quantity = $this->parseDecimal($trimmed);

            if ($quantity <= 0) {
                $this->sendMessage($chatId, 'Cantidad inválida. Escribe un número mayor a cero.', $this->buildGuidedCancelKeyboard());

                return true;
            }

            $currentItem['quantity'] = round($quantity, 2);
            $this->setGuidedState($chatId, 'item_unit_price', $draft, $currentItem);
            $this->sendMessage($chatId, 'Paso 6 de 8: escribe el precio unitario.', $this->buildGuidedCancelKeyboard());

            return true;
        }

        if ($step === 'item_unit_price') {
            $unitPrice = $this->parseAmountCandidate($trimmed);

            if ($unitPrice === null || $unitPrice <= 0) {
                $this->sendMessage($chatId, 'Precio unitario inválido. Escribe un monto mayor a cero.', $this->buildGuidedCancelKeyboard());

                return true;
            }

            $draft['items'] = is_array($draft['items'] ?? null) ? $draft['items'] : [];
            $draft['items'][] = [
                'description' => (string) ($currentItem['description'] ?? ''),
                'quantity' => (float) ($currentItem['quantity'] ?? 0),
                'unit_price' => round($unitPrice, 2),
            ];

            $this->setGuidedState($chatId, 'add_more_items', $draft, []);
            $this->sendMessage($chatId, '¿Deseas agregar otro concepto?', $this->buildGuidedAddMoreKeyboard());

            return true;
        }

        if ($step === 'add_more_items') {
            if ($this->isAffirmativeCommand($trimmed)) {
                $this->setGuidedState($chatId, 'item_description', $draft, []);
                $this->sendMessage($chatId, 'Captura la descripción del nuevo concepto.', $this->buildGuidedCancelKeyboard());

                return true;
            }

            if ($this->isNegativeCommand($trimmed)) {
                $this->setGuidedState($chatId, 'contact_name', $draft, []);
                $this->sendMessage($chatId, 'Paso 7 de 8: escribe el nombre de contacto. Puedes omitirlo.', $this->buildGuidedOptionalKeyboard());

                return true;
            }

            $this->sendMessage($chatId, 'Responde con sí o no para continuar.', $this->buildGuidedAddMoreKeyboard());

            return true;
        }

        if ($step === 'contact_name') {
            $draft['contact_name'] = $this->isSkipCommand($trimmed) ? '' : $trimmed;
            $this->setGuidedState($chatId, 'contact_email', $draft, []);
            $this->sendMessage($chatId, 'Paso 8 de 8: escribe el correo de contacto. Puedes omitirlo.', $this->buildGuidedOptionalKeyboard());

            return true;
        }

        if ($step === 'contact_email') {
            if ($this->isSkipCommand($trimmed)) {
                $draft['contact_email'] = '';
            } else {
                if (filter_var($trimmed, FILTER_VALIDATE_EMAIL) === false) {
                    $this->sendMessage($chatId, 'Correo inválido. Captura un correo válido o escribe omitir.', $this->buildGuidedOptionalKeyboard());

                    return true;
                }

                $draft['contact_email'] = $trimmed;
            }

            $this->setGuidedState($chatId, 'contact_phone', $draft, []);
            $this->sendMessage($chatId, 'Escribe el teléfono de contacto. Puedes omitirlo.', $this->buildGuidedOptionalKeyboard());

            return true;
        }

        if ($step === 'contact_phone') {
            $draft['contact_phone'] = $this->isSkipCommand($trimmed) ? '' : $trimmed;
            $this->setGuidedState($chatId, 'confirm', $draft, []);

            $this->sendMessage(
                $chatId,
                "Resumen preliminar:\n".
                "Cliente: ".($draft['client_name'] ?: 'Sin dato')."\n".
                "Proyecto: ".($draft['reference_code'] ?: 'Sin referencia')."\n".
                "Ubicación: ".($draft['location'] ?: 'Sin dato')."\n".
                "Conceptos: ".count(is_array($draft['items'] ?? null) ? $draft['items'] : [])."\n\n".
                "¿Deseas guardar la cotización?",
                $this->buildGuidedConfirmKeyboard()
            );

            return true;
        }

        if ($step === 'confirm') {
            if ($this->isAffirmativeCommand($trimmed)) {
                try {
                    $quote = $this->quoteAutomationService->createFromStructuredData($draft);
                } catch (Throwable $exception) {
                    $this->sendMessage(
                        $chatId,
                        'No fue posible guardar la cotización con los datos capturados. Revisa la información o inicia de nuevo.',
                        $this->buildGuidedConfirmKeyboard()
                    );

                    return true;
                }

                $this->clearState($chatId);
                $this->sendMessage(
                    $chatId,
                    "Cotización creada correctamente.\n".$this->buildQuoteSummary($quote, true),
                    $this->buildMainMenuKeyboard()
                );
                $this->sendQuotePdf($chatId, $quote, 'PDF de '.$this->pdfDisplayName($quote));

                return true;
            }

            if ($this->isNegativeCommand($trimmed)) {
                $this->clearState($chatId);
                $this->sendMainMenu($chatId, 'Creación guiada cancelada.');

                return true;
            }

            $this->sendMessage($chatId, 'Responde confirmar o cancelar para continuar.', $this->buildGuidedConfirmKeyboard());

            return true;
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $state
     */
    private function handleListBrowseMessage(string $chatId, string $text, array $state): bool
    {
        $currentPage = max(1, (int) ($state['page'] ?? 1));
        $search = isset($state['search']) ? (string) $state['search'] : null;

        if ($this->isPreviousPageCommand($text)) {
            $this->startQuoteListBrowse($chatId, max(1, $currentPage - 1), $search);

            return true;
        }

        if ($this->isNextPageCommand($text)) {
            $this->startQuoteListBrowse($chatId, $currentPage + 1, $search);

            return true;
        }

        if ($this->isSearchListCommand($text)) {
            $this->setState($chatId, [
                'action' => self::ACTION_LIST_SEARCH_INPUT,
                'page' => $currentPage,
                'search' => $search,
            ]);

            $this->sendMessage(
                $chatId,
                'Escribe el término a buscar (folio, cliente o proyecto).',
                $this->buildListSearchKeyboard()
            );

            return true;
        }

        if ($this->isClearSearchListCommand($text)) {
            $this->startQuoteListBrowse($chatId, 1);

            return true;
        }

        return false;
    }

    private function handleListSearchInputMessage(string $chatId, string $text): bool
    {
        if ($this->isClearSearchListCommand($text)) {
            $this->startQuoteListBrowse($chatId, 1);

            return true;
        }

        $search = trim($text);

        if ($search === '') {
            $this->sendMessage($chatId, 'Captura un término de búsqueda válido.', $this->buildListSearchKeyboard());

            return true;
        }

        $this->startQuoteListBrowse($chatId, 1, $search);

        return true;
    }

    private function setGuidedState(string $chatId, string $step, array $draft, array $currentItem): void
    {
        $this->setState($chatId, [
            'action' => self::ACTION_CREATE_GUIDED,
            'step' => $step,
            'draft' => $draft,
            'current_item' => $currentItem,
        ]);
    }

    /**
     * @param  array<string, mixed>  $state
     */
    private function handleSelectionPageNavigation(string $chatId, string $text, array $state, string $action, string $title): bool
    {
        $currentPage = max(1, (int) ($state['page'] ?? 1));

        if (!$this->isPreviousPageCommand($text) && !$this->isNextPageCommand($text)) {
            return false;
        }

        $targetPage = $this->isPreviousPageCommand($text) ? max(1, $currentPage - 1) : $currentPage + 1;
        $selection = $this->buildQuoteSelectionData($targetPage);

        $this->setState($chatId, [
            'action' => $action,
            'quote_options' => $selection['options'],
            'page' => $selection['page'],
        ]);

        $this->sendMessage(
            $chatId,
            $title."\n".
            "Puedes responder con número, folio, id o número de proyecto.\n\n".
            $selection['text'],
            $this->buildQuoteSelectionKeyboard($selection['options'], $selection['has_prev'], $selection['has_next'])
        );

        return true;
    }

    /**
     * @param  array<string, mixed>  $state
     */
    private function handleAddPaymentSelectQuote(string $chatId, string $text, array $state): bool
    {
        if ($this->handleSelectionPageNavigation(
            $chatId,
            $text,
            $state,
            self::ACTION_ADD_PAYMENT_SELECT_QUOTE,
            'Selecciona la cotización a la que deseas registrar anticipo.'
        )) {
            return true;
        }

        $quote = $this->resolveQuoteFromInput($text, $state['quote_options'] ?? []);

        if ($quote === null) {
            $selection = $this->buildQuoteSelectionData((int) ($state['page'] ?? 1));

            $this->sendMessage(
                $chatId,
                "No encontré esa cotización. Intenta de nuevo con número, folio, id o número de proyecto.\n\n".
                $selection['text'],
                $this->buildQuoteSelectionKeyboard($selection['options'], $selection['has_prev'], $selection['has_next'])
            );

            return true;
        }

        $this->setState($chatId, [
            'action' => self::ACTION_ADD_PAYMENT_COLLECT_DATA,
            'quote_id' => $quote->id,
        ]);

        $this->sendMessage(
            $chatId,
            "Seleccionaste {$quote->folio}.\n".
            $this->buildQuoteSummary($quote, false)."\n\n".
            "Ahora envía el anticipo.\n".
            "Formato recomendado:\n".
            "monto 15000, concepto Primer anticipo, fecha 2026-03-28, notas Transferencia\n\n".
            "La fecha y notas son opcionales.",
            $this->buildPaymentInputKeyboard()
        );

        return true;
    }

    /**
     * @param  array<string, mixed>  $state
     */
    private function handleAddPaymentCollectData(string $chatId, string $text, array $state): bool
    {
        $quoteId = (int) ($state['quote_id'] ?? 0);
        $quote = Quote::query()->find($quoteId);

        if ($quote === null) {
            $this->clearState($chatId);
            $this->sendMessage(
                $chatId,
                'La cotización seleccionada ya no existe. Inicia de nuevo la operación.',
                $this->buildMainMenuKeyboard()
            );

            return true;
        }

        $parsedPayment = $this->parsePaymentInput($text);

        if ($this->isShowPaymentFormatCommand($text)) {
            $this->sendMessage(
                $chatId,
                "Formato recomendado:\n".
                "monto 15000, concepto Primer anticipo, fecha 2026-03-28, notas Transferencia\n\n".
                "La fecha y notas son opcionales.",
                $this->buildPaymentInputKeyboard()
            );

            return true;
        }

        if ($parsedPayment === null) {
            $this->sendMessage(
                $chatId,
                "No pude leer el anticipo.\n".
                "Usa este formato:\n".
                "monto 15000, concepto Primer anticipo, fecha 2026-03-28, notas Transferencia",
                $this->buildPaymentInputKeyboard()
            );

            return true;
        }

        DB::transaction(function () use ($quote, $parsedPayment): void {
            $quote->payments()->create($parsedPayment);
            $quote->recalculateTotals();
        });

        $quote->refresh();
        $quote->load(['payments' => fn ($query) => $query->orderBy('received_at')->orderBy('id')]);

        $this->clearState($chatId);

        $this->sendMessage(
            $chatId,
            "Anticipo registrado correctamente en {$quote->folio}.\n".
            $this->buildQuoteSummary($quote, true),
            $this->buildMainMenuKeyboard()
        );

        $this->sendQuotePdf($chatId, $quote, 'PDF actualizado de '.$this->pdfDisplayName($quote));

        return true;
    }

    private function tryDirectAddPayment(string $chatId, string $text): bool
    {
        $quote = $this->resolveQuoteFromInput($text);
        $parsedPayment = $this->parsePaymentInput($text);

        if ($quote === null || $parsedPayment === null) {
            return false;
        }

        DB::transaction(function () use ($quote, $parsedPayment): void {
            $quote->payments()->create($parsedPayment);
            $quote->recalculateTotals();
        });

        $quote->refresh();
        $quote->load(['payments' => fn ($query) => $query->orderBy('received_at')->orderBy('id')]);

        $this->clearState($chatId);
        $this->sendMessage(
            $chatId,
            "Anticipo registrado en {$quote->folio}.\n".
            $this->buildQuoteSummary($quote, true),
            $this->buildMainMenuKeyboard()
        );
        $this->sendQuotePdf($chatId, $quote, 'PDF actualizado de '.$this->pdfDisplayName($quote));

        return true;
    }

    private function tryDirectSendPdf(string $chatId, string $text): bool
    {
        $quote = $this->resolveQuoteFromInput($text);

        if ($quote === null) {
            return false;
        }

        $this->clearState($chatId);
        $this->sendMessage(
            $chatId,
            "Te envío el PDF de {$this->pdfDisplayName($quote)}.\n".
            $this->buildQuoteSummary($quote, true),
            $this->buildMainMenuKeyboard()
        );
        $this->sendQuotePdf($chatId, $quote, 'PDF de '.$this->pdfDisplayName($quote));

        return true;
    }

    /**
     * @param  array<string, mixed>  $state
     */
    private function handleEditSelectQuote(string $chatId, string $text, array $state): bool
    {
        if ($this->handleSelectionPageNavigation(
            $chatId,
            $text,
            $state,
            self::ACTION_EDIT_SELECT_QUOTE,
            'Selecciona la cotización que deseas editar.'
        )) {
            return true;
        }

        $quote = $this->resolveQuoteFromInput($text, $state['quote_options'] ?? []);

        if ($quote === null) {
            $selection = $this->buildQuoteSelectionData((int) ($state['page'] ?? 1));

            $this->sendMessage(
                $chatId,
                "No encontré esa cotización. Intenta de nuevo con número, folio, id o número de proyecto.\n\n".
                $selection['text'],
                $this->buildQuoteSelectionKeyboard($selection['options'], $selection['has_prev'], $selection['has_next'])
            );

            return true;
        }

        $this->setState($chatId, [
            'action' => self::ACTION_EDIT_CHOOSE_FIELD,
            'quote_id' => $quote->id,
        ]);

        $this->sendMessage(
            $chatId,
            "Seleccionaste {$quote->folio}.\n".
            $this->buildEditableDataSnapshot($quote)."\n\n".
            $this->buildEditableFieldsHelp(),
            $this->buildEditFieldsKeyboard()
        );

        return true;
    }

    /**
     * @param  array<string, mixed>  $state
     */
    private function handleSendPdfSelectQuote(string $chatId, string $text, array $state): bool
    {
        if ($this->handleSelectionPageNavigation(
            $chatId,
            $text,
            $state,
            self::ACTION_SEND_PDF_SELECT_QUOTE,
            'Selecciona la cotización de la cual deseas reenviar el PDF.'
        )) {
            return true;
        }

        $quote = $this->resolveQuoteFromInput($text, $state['quote_options'] ?? []);

        if ($quote === null) {
            $selection = $this->buildQuoteSelectionData((int) ($state['page'] ?? 1));

            $this->sendMessage(
                $chatId,
                "No encontré esa cotización. Intenta de nuevo con número, folio, id o número de proyecto.\n\n".
                $selection['text'],
                $this->buildQuoteSelectionKeyboard($selection['options'], $selection['has_prev'], $selection['has_next'])
            );

            return true;
        }

        $this->clearState($chatId);
        $this->sendMessage(
            $chatId,
            "Te envío el PDF de {$this->pdfDisplayName($quote)}.\n".
            $this->buildQuoteSummary($quote, true),
            $this->buildMainMenuKeyboard()
        );
        $this->sendQuotePdf($chatId, $quote, 'PDF de '.$this->pdfDisplayName($quote));

        return true;
    }

    /**
     * @param  array<string, mixed>  $state
     */
    private function handleEditChooseField(string $chatId, string $text, array $state): bool
    {
        $quoteId = (int) ($state['quote_id'] ?? 0);
        $quote = Quote::query()->find($quoteId);

        if ($quote === null) {
            $this->clearState($chatId);
            $this->sendMessage(
                $chatId,
                'La cotización seleccionada ya no existe. Inicia de nuevo la operación.',
                $this->buildMainMenuKeyboard()
            );

            return true;
        }

        if ($this->isFinishEditCommand($text)) {
            $this->clearState($chatId);
            $this->sendMessage(
                $chatId,
                'Edición finalizada. Te envío el PDF actualizado.',
                $this->buildMainMenuKeyboard()
            );
            $this->sendQuotePdf($chatId, $quote, 'PDF actualizado de '.$this->pdfDisplayName($quote));

            return true;
        }

        $fieldSelection = $this->resolveEditableField($text);

        if ($fieldSelection === null) {
            $this->sendMessage(
                $chatId,
                'No reconozco ese campo.\n\n'.$this->buildEditableFieldsHelp(),
                $this->buildEditFieldsKeyboard()
            );

            return true;
        }

        $fieldName = $fieldSelection['field'];
        $label = $fieldSelection['label'];
        $currentValue = $this->formatCurrentFieldValue($quote, $fieldName);

        $this->setState($chatId, [
            'action' => self::ACTION_EDIT_SET_VALUE,
            'quote_id' => $quote->id,
            'field' => $fieldName,
            'label' => $label,
        ]);

        $this->sendMessage(
            $chatId,
            "Campo seleccionado: {$label}.\n".
            "Valor actual: {$currentValue}\n\n".
            "Envía el nuevo valor.\n".
            "Si deseas dejarlo vacío, escribe: vacio",
            $this->buildEditValueKeyboard()
        );

        return true;
    }

    /**
     * @param  array<string, mixed>  $state
     */
    private function handleEditSetValue(string $chatId, string $text, array $state): bool
    {
        $quoteId = (int) ($state['quote_id'] ?? 0);
        $field = (string) ($state['field'] ?? '');
        $label = (string) ($state['label'] ?? $field);

        $quote = Quote::query()->find($quoteId);

        if ($quote === null) {
            $this->clearState($chatId);
            $this->sendMessage(
                $chatId,
                'La cotización seleccionada ya no existe. Inicia de nuevo la operación.',
                $this->buildMainMenuKeyboard()
            );

            return true;
        }

        $newValue = trim($text);
        $normalized = mb_strtolower($newValue);

        if (in_array($normalized, ['vacio', 'vacío', 'null'], true)) {
            $newValue = '';
        }

        if ($this->isBackToEditFieldsCommand($newValue)) {
            $this->setState($chatId, [
                'action' => self::ACTION_EDIT_CHOOSE_FIELD,
                'quote_id' => $quote->id,
            ]);

            $this->sendMessage(
                $chatId,
                $this->buildEditableDataSnapshot($quote)."\n\n".$this->buildEditableFieldsHelp(),
                $this->buildEditFieldsKeyboard()
            );

            return true;
        }

        try {
            $payload = $this->buildEditPayload($field, $newValue);
        } catch (RuntimeException $exception) {
            $this->sendMessage(
                $chatId,
                $exception->getMessage(),
                $this->buildEditValueKeyboard()
            );

            return true;
        }

        $quote->update($payload);
        $quote->refresh();

        $this->setState($chatId, [
            'action' => self::ACTION_EDIT_CHOOSE_FIELD,
            'quote_id' => $quote->id,
        ]);

        $this->sendMessage(
            $chatId,
            "Campo actualizado: {$label}.\n".
            $this->buildEditableDataSnapshot($quote)."\n\n".
            $this->buildEditableFieldsHelp(),
            $this->buildEditFieldsKeyboard()
        );

        return true;
    }

    private function sendQuotePdf(string $chatId, Quote $quote, string $caption): void
    {
        $pdfPath = $this->quoteAutomationService->buildPdfForQuote($quote);
        $this->telegramClient->sendDocument($chatId, $pdfPath, $caption);

        if (is_file($pdfPath)) {
            @unlink($pdfPath);
        }
    }

    private function pdfDisplayName(Quote $quote): string
    {
        return $quote->pdfFileBaseName();
    }

    private function buildQuoteSummary(Quote $quote, bool $includePayments): string
    {
        $quote->loadMissing(['payments' => fn ($query) => $query->orderBy('received_at')->orderBy('id')]);

        $lines = [
            'Cotización: '.$quote->folio,
            'Cliente: '.$quote->client_name,
            'Proyecto: '.($quote->reference_code ?: 'Sin referencia'),
            'Total: $'.number_format((float) $quote->total, 2).' + IVA',
            'Total recibido: $'.number_format((float) $quote->paid_total, 2).' + IVA',
            'Saldo pendiente: $'.number_format((float) $quote->balance_due, 2).' + IVA',
        ];

        if ($includePayments) {
            if ($quote->payments->isEmpty()) {
                $lines[] = 'Anticipos: no hay registros.';
            } else {
                $lines[] = 'Anticipos registrados:';

                foreach ($quote->payments as $payment) {
                    $lines[] =
                        '- '.($payment->label ?: 'Anticipo').
                        ' | $'.number_format((float) $payment->amount, 2).' + IVA'.
                        ' | '.$payment->received_at->format('Y-m-d');
                }
            }
        }

        return implode("\n", $lines);
    }

    private function buildEditableDataSnapshot(Quote $quote): string
    {
        return implode("\n", [
            'Datos actuales:',
            '1) Cliente: '.($quote->client_name ?: 'Sin dato'),
            '2) Número de proyecto: '.($quote->reference_code ?: 'Sin dato'),
            '3) Ubicación: '.($quote->location ?: 'Sin dato'),
            '4) Fecha: '.$quote->issued_at->format('Y-m-d'),
            '5) Términos: '.($quote->terms ?: 'Sin dato'),
            '6) Contacto (nombre): '.($quote->contact_name ?: 'Sin dato'),
            '7) Contacto (correo): '.($quote->contact_email ?: 'Sin dato'),
            '8) Contacto (teléfono): '.($quote->contact_phone ?: 'Sin dato'),
        ]);
    }

    private function buildEditableFieldsHelp(): string
    {
        return "¿Qué campo deseas editar?\n".
            "Responde con número o nombre del campo.\n".
            "Para terminar, escribe: finalizar";
    }

    /**
     * @return array{field:string,label:string}|null
     */
    private function resolveEditableField(string $input): ?array
    {
        $normalized = mb_strtolower(trim($input));

        if ($this->matchesMenuOption($normalized, 1)) {
            return ['field' => 'client_name', 'label' => 'Cliente'];
        }

        if ($this->matchesMenuOption($normalized, 2)) {
            return ['field' => 'reference_code', 'label' => 'Número de proyecto'];
        }

        if ($this->matchesMenuOption($normalized, 3)) {
            return ['field' => 'location', 'label' => 'Ubicación'];
        }

        if ($this->matchesMenuOption($normalized, 4)) {
            return ['field' => 'issued_at', 'label' => 'Fecha'];
        }

        if ($this->matchesMenuOption($normalized, 5)) {
            return ['field' => 'terms', 'label' => 'Términos'];
        }

        if ($this->matchesMenuOption($normalized, 6)) {
            return ['field' => 'contact_name', 'label' => 'Contacto (nombre)'];
        }

        if ($this->matchesMenuOption($normalized, 7)) {
            return ['field' => 'contact_email', 'label' => 'Contacto (correo)'];
        }

        if ($this->matchesMenuOption($normalized, 8)) {
            return ['field' => 'contact_phone', 'label' => 'Contacto (teléfono)'];
        }

        return match (true) {
            in_array($normalized, ['1', 'cliente'], true) => ['field' => 'client_name', 'label' => 'Cliente'],
            in_array($normalized, ['2', 'proyecto', 'pedido', 'referencia', 'numero de proyecto', 'número de proyecto'], true) => ['field' => 'reference_code', 'label' => 'Número de proyecto'],
            in_array($normalized, ['3', 'ubicacion', 'ubicación'], true) => ['field' => 'location', 'label' => 'Ubicación'],
            in_array($normalized, ['4', 'fecha'], true) => ['field' => 'issued_at', 'label' => 'Fecha'],
            in_array($normalized, ['5', 'terminos', 'términos'], true) => ['field' => 'terms', 'label' => 'Términos'],
            in_array($normalized, ['6', 'contacto', 'contacto nombre', 'nombre contacto'], true) => ['field' => 'contact_name', 'label' => 'Contacto (nombre)'],
            in_array($normalized, ['7', 'correo', 'email', 'contacto correo'], true) => ['field' => 'contact_email', 'label' => 'Contacto (correo)'],
            in_array($normalized, ['8', 'telefono', 'teléfono', 'contacto telefono', 'contacto teléfono'], true) => ['field' => 'contact_phone', 'label' => 'Contacto (teléfono)'],
            default => null,
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function buildEditPayload(string $field, string $newValue): array
    {
        if ($field === 'client_name' && $newValue === '') {
            throw new RuntimeException('El cliente no puede quedar vacío.');
        }

        if ($field === 'issued_at') {
            if ($newValue === '') {
                throw new RuntimeException('La fecha no puede quedar vacía. Usa formato YYYY-MM-DD.');
            }

            $timestamp = strtotime($newValue);

            if ($timestamp === false) {
                throw new RuntimeException('Fecha inválida. Usa formato YYYY-MM-DD.');
            }

            return [$field => date('Y-m-d', $timestamp)];
        }

        if ($field === 'contact_email' && $newValue !== '' && filter_var($newValue, FILTER_VALIDATE_EMAIL) === false) {
            throw new RuntimeException('Correo inválido. Captura un email válido o escribe vacio.');
        }

        return [$field => $newValue === '' ? null : $newValue];
    }

    private function formatCurrentFieldValue(Quote $quote, string $field): string
    {
        if ($field === 'issued_at') {
            return $quote->issued_at->format('Y-m-d');
        }

        $value = $quote->{$field};

        return $value === null || $value === '' ? 'Sin dato' : (string) $value;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function getState(string $chatId): ?array
    {
        $state = Cache::get($this->stateKey($chatId));

        return is_array($state) ? $state : null;
    }

    /**
     * @param  array<string, mixed>  $state
     */
    private function setState(string $chatId, array $state): void
    {
        Cache::forever($this->stateKey($chatId), $state);
    }

    private function clearState(string $chatId): void
    {
        Cache::forget($this->stateKey($chatId));
    }

    private function stateKey(string $chatId): string
    {
        return self::STATE_KEY_PREFIX.$chatId;
    }

    /**
     * @param  mixed  $optionsInput
     */
    private function resolveQuoteFromInput(string $input, mixed $optionsInput = []): ?Quote
    {
        $trimmed = trim($input);
        $options = is_array($optionsInput) ? $optionsInput : [];

        if ($trimmed === '') {
            return null;
        }

        if (ctype_digit($trimmed) && isset($options[$trimmed])) {
            return Quote::query()->find((int) $options[$trimmed]);
        }

        if (preg_match('/COT-\d{1,6}/i', $trimmed, $matches) === 1) {
            return Quote::query()->where('folio', strtoupper($matches[0]))->first();
        }

        if (preg_match('/\b\d+\b/', $trimmed, $idMatch) === 1 && ctype_digit($idMatch[0])) {
            $quote = Quote::query()->find((int) $idMatch[0]);

            if ($quote !== null) {
                return $quote;
            }
        }

        return Quote::query()
            ->where('reference_code', $trimmed)
            ->orWhere('reference_code', strtoupper($trimmed))
            ->orWhere('reference_code', strtolower($trimmed))
            ->latest('id')
            ->first();
    }

    /**
     * @return array<string, mixed>|null
     */
    private function parsePaymentInput(string $text): ?array
    {
        $amount = $this->extractAmountFromText($text);

        if ($amount === null || $amount <= 0) {
            return null;
        }

        $concept = 'Anticipo';
        $notes = null;
        $date = now()->toDateString();

        if (preg_match('/concepto\s*[:=]?\s*([^,\n]+)/iu', $text, $matches) === 1) {
            $candidate = trim($matches[1]);

            if ($candidate !== '') {
                $concept = $candidate;
            }
        }

        if (preg_match('/(?:nota|notas)\s*[:=]?\s*(.+)$/iu', $text, $matches) === 1) {
            $candidate = trim($matches[1]);
            $notes = $candidate !== '' ? $candidate : null;
        }

        if (preg_match('/\b(\d{4}-\d{2}-\d{2})\b/', $text, $matches) === 1) {
            $timestamp = strtotime($matches[1]);

            if ($timestamp !== false) {
                $date = date('Y-m-d', $timestamp);
            }
        } elseif (preg_match('/\b(\d{2}\/\d{2}\/\d{4})\b/', $text, $matches) === 1) {
            $timestamp = strtotime(str_replace('/', '-', $matches[1]));

            if ($timestamp !== false) {
                $date = date('Y-m-d', $timestamp);
            }
        }

        return [
            'label' => $concept,
            'amount' => round($amount, 2),
            'received_at' => $date,
            'notes' => $notes,
        ];
    }

    private function extractAmountFromText(string $text): ?float
    {
        $cleanText = preg_replace('/COT-\d{1,6}/i', ' ', $text) ?? $text;
        $cleanText = preg_replace('/\b\d{4}-\d{2}-\d{2}\b/', ' ', $cleanText) ?? $cleanText;
        $cleanText = preg_replace('/\b\d{2}\/\d{2}\/\d{4}\b/', ' ', $cleanText) ?? $cleanText;

        if (preg_match('/(?:monto|importe)\s*[:=]?\s*([^\n]+)/iu', $cleanText, $matches) === 1) {
            $parsed = $this->parseAmountCandidate($matches[1]);

            if ($parsed !== null && $parsed > 0) {
                return $parsed;
            }
        }

        if (preg_match('/(?:anticipo|pago)\s+de\s+([^\n]+)/iu', $cleanText, $matches) === 1) {
            $parsed = $this->parseAmountCandidate($matches[1]);

            if ($parsed !== null && $parsed > 0) {
                return $parsed;
            }
        }

        if (preg_match('/\$\s*([\d.,\s]+(?:\s*(?:mil|miles|mill[oó]n(?:es)?))?)/iu', $cleanText, $matches) === 1) {
            $parsed = $this->parseAmountCandidate($matches[1]);

            if ($parsed !== null && $parsed > 0) {
                return $parsed;
            }
        }

        if (preg_match('/\b([\d][\d.,\s]*(?:\s*(?:mil|miles|mill[oó]n(?:es)?))?)\b/iu', $cleanText, $matches) === 1) {
            $parsed = $this->parseAmountCandidate($matches[1]);

            if ($parsed !== null && $parsed > 0) {
                return $parsed;
            }
        }

        return $this->parseSpanishNumberWords($cleanText);
    }

    private function parseAmountCandidate(string $candidate): ?float
    {
        $normalized = mb_strtolower(trim($candidate));

        if ($normalized === '') {
            return null;
        }

        $multiplier = 1.0;

        if (preg_match('/\bmil(?:es)?\b/u', $normalized) === 1) {
            $multiplier = 1000.0;
            $normalized = preg_replace('/\bmil(?:es)?\b/u', '', $normalized) ?? $normalized;
        }

        if (preg_match('/\bmill[oó]n(?:es)?\b/u', $normalized) === 1) {
            $multiplier = 1000000.0;
            $normalized = preg_replace('/\bmill[oó]n(?:es)?\b/u', '', $normalized) ?? $normalized;
        }

        $normalized = trim($normalized);

        if ($normalized === '') {
            return null;
        }

        if (preg_match('/\d/u', $normalized) === 1) {
            $value = $this->parseDecimal($normalized);

            return $value > 0 ? $value * $multiplier : null;
        }

        $wordValue = $this->parseSpanishNumberWords($normalized);

        if ($wordValue === null) {
            return null;
        }

        return $wordValue * $multiplier;
    }

    private function parseDecimal(string $value): float
    {
        $clean = preg_replace('/[^\d,\.\-]/', '', trim($value)) ?? '0';

        if ($clean === '' || $clean === '-' || $clean === '.' || $clean === ',') {
            return 0.0;
        }

        $commaCount = substr_count($clean, ',');
        $dotCount = substr_count($clean, '.');

        if ($commaCount > 0 && $dotCount > 0) {
            $lastComma = strrpos($clean, ',');
            $lastDot = strrpos($clean, '.');

            if ($lastComma !== false && $lastDot !== false && $lastComma > $lastDot) {
                $clean = str_replace('.', '', $clean);
                $clean = str_replace(',', '.', $clean);
            } else {
                $clean = str_replace(',', '', $clean);
            }

            return (float) $clean;
        }

        if ($commaCount > 0) {
            if ($commaCount > 1) {
                $clean = str_replace(',', '', $clean);

                return (float) $clean;
            }

            [$left, $right] = array_pad(explode(',', $clean, 2), 2, '');

            if (strlen($right) === 3 && strlen($left) >= 1) {
                $clean = $left.$right;
            } else {
                $clean = $left.'.'.$right;
            }

            return (float) $clean;
        }

        if ($dotCount > 0) {
            if ($dotCount > 1) {
                $clean = str_replace('.', '', $clean);

                return (float) $clean;
            }

            [$left, $right] = array_pad(explode('.', $clean, 2), 2, '');

            if (strlen($right) === 3 && strlen($left) >= 1) {
                $clean = $left.$right;
            }

            return (float) $clean;
        }

        return (float) $clean;
    }

    private function parseSpanishNumberWords(string $text): ?float
    {
        $normalized = mb_strtolower(trim($text));

        if ($normalized === '') {
            return null;
        }

        $normalized = strtr($normalized, [
            'á' => 'a',
            'é' => 'e',
            'í' => 'i',
            'ó' => 'o',
            'ú' => 'u',
            'ü' => 'u',
            'ñ' => 'n',
        ]);
        $normalized = preg_replace('/[^a-z\s]/', ' ', $normalized) ?? $normalized;
        $normalized = preg_replace('/\s+/', ' ', $normalized) ?? $normalized;
        $normalized = trim($normalized);

        if ($normalized === '') {
            return null;
        }

        $units = [
            'cero' => 0,
            'un' => 1,
            'uno' => 1,
            'una' => 1,
            'dos' => 2,
            'tres' => 3,
            'cuatro' => 4,
            'cinco' => 5,
            'seis' => 6,
            'siete' => 7,
            'ocho' => 8,
            'nueve' => 9,
            'diez' => 10,
            'once' => 11,
            'doce' => 12,
            'trece' => 13,
            'catorce' => 14,
            'quince' => 15,
            'dieciseis' => 16,
            'diecisiete' => 17,
            'dieciocho' => 18,
            'diecinueve' => 19,
            'veinte' => 20,
            'veintiuno' => 21,
            'veintidos' => 22,
            'veintitres' => 23,
            'veinticuatro' => 24,
            'veinticinco' => 25,
            'veintiseis' => 26,
            'veintisiete' => 27,
            'veintiocho' => 28,
            'veintinueve' => 29,
        ];

        $tens = [
            'treinta' => 30,
            'cuarenta' => 40,
            'cincuenta' => 50,
            'sesenta' => 60,
            'setenta' => 70,
            'ochenta' => 80,
            'noventa' => 90,
        ];

        $hundreds = [
            'cien' => 100,
            'ciento' => 100,
            'doscientos' => 200,
            'trescientos' => 300,
            'cuatrocientos' => 400,
            'quinientos' => 500,
            'seiscientos' => 600,
            'setecientos' => 700,
            'ochocientos' => 800,
            'novecientos' => 900,
        ];

        $scales = [
            'mil' => 1000,
            'miles' => 1000,
            'millon' => 1000000,
            'millones' => 1000000,
        ];

        $tokens = explode(' ', $normalized);
        $current = 0;
        $total = 0;
        $matched = false;

        foreach ($tokens as $token) {
            if ($token === '' || $token === 'y') {
                continue;
            }

            if (isset($units[$token])) {
                $current += $units[$token];
                $matched = true;

                continue;
            }

            if (isset($tens[$token])) {
                $current += $tens[$token];
                $matched = true;

                continue;
            }

            if (isset($hundreds[$token])) {
                $current += $hundreds[$token];
                $matched = true;

                continue;
            }

            if (isset($scales[$token])) {
                $scale = $scales[$token];
                $base = $current === 0 ? 1 : $current;
                $total += $base * $scale;
                $current = 0;
                $matched = true;

                continue;
            }
        }

        if (!$matched) {
            return null;
        }

        $value = $total + $current;

        return $value > 0 ? (float) $value : null;
    }

    /**
     * @param  array<string, mixed>|null  $replyMarkup
     */
    private function sendMessage(string $chatId, string $text, ?array $replyMarkup = null): void
    {
        $this->telegramClient->sendMessageWithMarkup(
            $chatId,
            $text,
            $this->toInlineKeyboardMarkup($replyMarkup)
        );
    }

    /**
     * @param  array<string, mixed>|null  $replyMarkup
     */
    private function toInlineKeyboardMarkup(?array $replyMarkup): ?array
    {
        if ($replyMarkup === null) {
            return null;
        }

        $rows = $replyMarkup['keyboard'] ?? null;

        if (!is_array($rows) || $rows === []) {
            return null;
        }

        $inlineRows = [];

        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $inlineRow = [];

            foreach ($row as $button) {
                if (!is_array($button)) {
                    continue;
                }

                $label = trim((string) ($button['text'] ?? ''));

                if ($label !== '') {
                    $inlineRow[] = [
                        'text' => $label,
                        'callback_data' => $this->buildCallbackData($label),
                    ];
                }
            }

            if ($inlineRow !== []) {
                $inlineRows[] = $inlineRow;
            }
        }

        if ($inlineRows === []) {
            return null;
        }

        return [
            'inline_keyboard' => $inlineRows,
        ];
    }

    private function buildCallbackData(string $label): string
    {
        $trimmed = trim($label);

        if ($trimmed === '') {
            return 'menu';
        }

        if (strlen($trimmed) <= 64) {
            return $trimmed;
        }

        return mb_strcut($trimmed, 0, 64, 'UTF-8');
    }

    private function handleMainMenuOption(string $chatId, string $text, User $linkedUser): bool
    {
        $normalized = mb_strtolower(trim($text));

        if ($this->matchesMenuOption($normalized, 1)) {
            if (!$this->ensurePermission($chatId, $linkedUser, AppPermissions::QUOTES_CREATE)) {
                return true;
            }

            $this->startGuidedCreateFlow($chatId);

            return true;
        }

        if ($this->matchesMenuOption($normalized, 2)) {
            if (!$this->ensurePermission($chatId, $linkedUser, AppPermissions::QUOTES_VIEW)) {
                return true;
            }

            $this->sendQuoteList($chatId);

            return true;
        }

        if ($this->matchesMenuOption($normalized, 3)) {
            if (!$this->ensurePermission($chatId, $linkedUser, AppPermissions::QUOTES_VIEW)) {
                return true;
            }

            $this->startSendPdfFlow($chatId);

            return true;
        }

        if ($this->matchesMenuOption($normalized, 4)) {
            if (!$this->ensurePermission($chatId, $linkedUser, AppPermissions::QUOTES_EDIT)) {
                return true;
            }

            $this->startAddPaymentFlow($chatId);

            return true;
        }

        if ($this->matchesMenuOption($normalized, 5)) {
            if (!$this->ensurePermission($chatId, $linkedUser, AppPermissions::QUOTES_EDIT)) {
                return true;
            }

            $this->startEditQuoteFlow($chatId);

            return true;
        }

        if ($this->matchesMenuOption($normalized, 6)) {
            $this->sendMainMenu($chatId, $this->helpMessage());

            return true;
        }

        return false;
    }

    private function sendMainMenu(string $chatId, string $intro): void
    {
        $this->sendMessage(
            $chatId,
            $intro."\n\nEscribe una opción del menú:",
            $this->buildMainMenuKeyboard()
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function buildMainMenuKeyboard(): array
    {
        return [
            'keyboard' => [
                [
                    ['text' => '1 Crear cotización'],
                    ['text' => '2 Listar cotizaciones'],
                ],
                [
                    ['text' => '3 Reenviar PDF'],
                    ['text' => '4 Agregar anticipo'],
                ],
                [
                    ['text' => '5 Editar cotización'],
                    ['text' => '6 Ayuda'],
                ],
            ],
            'resize_keyboard' => true,
            'one_time_keyboard' => false,
            'is_persistent' => true,
        ];
    }

    /**
     * @param  array<string, int>  $options
     * @return array<string, mixed>
     */
    private function buildQuoteSelectionKeyboard(array $options, bool $hasPrev = false, bool $hasNext = false): array
    {
        $rows = [];
        $buffer = [];

        foreach (array_keys($options) as $optionNumber) {
            $buffer[] = ['text' => $optionNumber];

            if (count($buffer) === 4) {
                $rows[] = $buffer;
                $buffer = [];
            }
        }

        if ($buffer !== []) {
            $rows[] = $buffer;
        }

        if ($hasPrev || $hasNext) {
            $navigationRow = [];

            if ($hasPrev) {
                $navigationRow[] = ['text' => '7 Anterior'];
            }

            if ($hasNext) {
                $navigationRow[] = ['text' => '8 Siguiente'];
            }

            if ($navigationRow !== []) {
                $rows[] = $navigationRow;
            }
        }

        $rows[] = [
            ['text' => '6 Ayuda'],
        ];

        return [
            'keyboard' => $rows,
            'resize_keyboard' => true,
            'one_time_keyboard' => false,
            'is_persistent' => true,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildListActionsKeyboard(bool $hasPrev = false, bool $hasNext = false, bool $hasSearch = false): array
    {
        $navigationRow = [];

        if ($hasPrev) {
            $navigationRow[] = ['text' => '7 Anterior'];
        }

        if ($hasNext) {
            $navigationRow[] = ['text' => '8 Siguiente'];
        }

        $searchRow = [
            ['text' => '9 Buscar'],
        ];

        if ($hasSearch) {
            $searchRow[] = ['text' => '10 Limpiar búsqueda'];
        }

        $rows = [];

        if ($navigationRow !== []) {
            $rows[] = $navigationRow;
        }

        $rows[] = $searchRow;
        $rows[] = [
            ['text' => '3 Reenviar PDF'],
            ['text' => '4 Agregar anticipo'],
        ];
        $rows[] = [
            ['text' => '5 Editar cotización'],
            ['text' => '1 Crear cotización'],
        ];
        $rows[] = [
            ['text' => '6 Ayuda'],
        ];

        return [
            'keyboard' => $rows,
            'resize_keyboard' => true,
            'one_time_keyboard' => false,
            'is_persistent' => true,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildPaymentInputKeyboard(): array
    {
        return [
            'keyboard' => [
                [
                    ['text' => '9 Ver formato'],
                ],
            ],
            'resize_keyboard' => true,
            'one_time_keyboard' => false,
            'is_persistent' => true,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildEditFieldsKeyboard(): array
    {
        return [
            'keyboard' => [
                [
                    ['text' => '1 Cliente'],
                    ['text' => '2 Proyecto'],
                ],
                [
                    ['text' => '3 Ubicación'],
                    ['text' => '4 Fecha'],
                ],
                [
                    ['text' => '5 Términos'],
                    ['text' => '6 Contacto nombre'],
                ],
                [
                    ['text' => '7 Contacto correo'],
                    ['text' => '8 Contacto teléfono'],
                ],
                [
                    ['text' => '9 Finalizar edición'],
                ],
            ],
            'resize_keyboard' => true,
            'one_time_keyboard' => false,
            'is_persistent' => true,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildEditValueKeyboard(): array
    {
        return [
            'keyboard' => [
                [
                    ['text' => 'vacio'],
                    ['text' => '9 Volver a campos'],
                ],
            ],
            'resize_keyboard' => true,
            'one_time_keyboard' => false,
            'is_persistent' => true,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildGuidedCancelKeyboard(): array
    {
        return [
            'keyboard' => [],
            'resize_keyboard' => true,
            'one_time_keyboard' => false,
            'is_persistent' => true,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildGuidedOptionalKeyboard(): array
    {
        return [
            'keyboard' => [
                [
                    ['text' => '1 Omitir'],
                ],
            ],
            'resize_keyboard' => true,
            'one_time_keyboard' => false,
            'is_persistent' => true,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildGuidedAddMoreKeyboard(): array
    {
        return [
            'keyboard' => [
                [
                    ['text' => '1 Sí, agregar otro concepto'],
                ],
                [
                    ['text' => '2 No, continuar'],
                ],
            ],
            'resize_keyboard' => true,
            'one_time_keyboard' => false,
            'is_persistent' => true,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildGuidedConfirmKeyboard(): array
    {
        return [
            'keyboard' => [
                [
                    ['text' => '1 Confirmar cotización'],
                ],
                [
                    ['text' => '2 Cancelar creación'],
                ],
            ],
            'resize_keyboard' => true,
            'one_time_keyboard' => false,
            'is_persistent' => true,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildListSearchKeyboard(): array
    {
        return [
            'keyboard' => [
                [
                    ['text' => '10 Limpiar búsqueda'],
                ],
            ],
            'resize_keyboard' => true,
            'one_time_keyboard' => false,
            'is_persistent' => true,
        ];
    }

    private function matchesMenuOption(string $text, int $option): bool
    {
        return preg_match('/^'.preg_quote((string) $option, '/').'\b/u', $text) === 1;
    }

    private function isLinkCommand(string $text): bool
    {
        $normalized = mb_strtolower(trim($text));

        return str_starts_with($normalized, '/vincular') || str_starts_with($normalized, 'vincular ');
    }

    private function processLinkCommand(string $chatId, string $text): void
    {
        $code = $this->extractLinkCodeFromText($text);

        if ($code === null) {
            $this->sendMessage(
                $chatId,
                "Debes enviar un código de vinculación válido.\n".
                "Formato: /vincular CODIGO",
                $this->buildGuidedCancelKeyboard()
            );

            return;
        }

        $linkedUser = $this->telegramUserLinkService->linkChatByCode($chatId, $code);

        if ($linkedUser === null) {
            $this->sendMessage(
                $chatId,
                'El código no es válido o ya venció. Genera uno nuevo desde tu perfil en la aplicación.',
                $this->buildGuidedCancelKeyboard()
            );

            return;
        }

        $this->clearState($chatId);
        $this->sendMainMenu(
            $chatId,
            'Vinculación completada. Ya puedes usar el bot con los permisos de tu usuario '.$linkedUser->name.'.'
        );
    }

    private function sendLinkRequiredMessage(string $chatId): void
    {
        $this->sendMessage(
            $chatId,
            "Este chat no está vinculado a una cuenta del sistema.\n".
            "1) Inicia sesión en la aplicación.\n".
            "2) En tu perfil, genera un código de vinculación.\n".
            "3) Aquí escribe: /vincular CODIGO",
            $this->buildGuidedCancelKeyboard()
        );
    }

    private function extractLinkCodeFromText(string $text): ?string
    {
        $trimmed = trim($text);

        if (preg_match('/^\/?vincular\s+([a-zA-Z0-9-]+)$/u', $trimmed, $matches) !== 1) {
            return null;
        }

        return strtoupper(trim((string) $matches[1]));
    }

    private function ensurePermission(string $chatId, User $linkedUser, string $permission, bool $fromState = false): bool
    {
        if ($linkedUser->can($permission)) {
            return true;
        }

        $message = $fromState
            ? 'Tu usuario ya no tiene permisos para continuar este flujo. Solicita acceso a un administrador.'
            : 'Tu usuario no tiene permisos para ejecutar esta acción. Solicita acceso a un administrador.';

        $this->sendMessage($chatId, $message, $this->buildMainMenuKeyboard());

        return false;
    }

    private function isShowPaymentFormatCommand(string $text): bool
    {
        $normalized = mb_strtolower(trim($text));

        return $this->matchesMenuOption($normalized, 9) ||
            str_contains($normalized, 'ver formato');
    }

    private function isBackToEditFieldsCommand(string $text): bool
    {
        $normalized = mb_strtolower(trim($text));

        return $this->matchesMenuOption($normalized, 9) ||
            str_contains($normalized, 'volver a campos');
    }

    private function isPreviousPageCommand(string $text): bool
    {
        $normalized = mb_strtolower(trim($text));

        return $this->matchesMenuOption($normalized, 7) || str_contains($normalized, 'anterior');
    }

    private function isNextPageCommand(string $text): bool
    {
        $normalized = mb_strtolower(trim($text));

        return $this->matchesMenuOption($normalized, 8) || str_contains($normalized, 'siguiente');
    }

    private function isSearchListCommand(string $text): bool
    {
        $normalized = mb_strtolower(trim($text));

        return $this->matchesMenuOption($normalized, 9) || str_contains($normalized, 'buscar');
    }

    private function isClearSearchListCommand(string $text): bool
    {
        $normalized = mb_strtolower(trim($text));

        return $this->matchesMenuOption($normalized, 10) || str_contains($normalized, 'limpiar búsqueda') || str_contains($normalized, 'limpiar busqueda');
    }

    private function isSkipCommand(string $text): bool
    {
        $normalized = mb_strtolower(trim($text));

        return $this->matchesMenuOption($normalized, 1) || in_array($normalized, ['omitir', 'saltar', 'skip'], true);
    }

    private function isAffirmativeCommand(string $text): bool
    {
        $normalized = mb_strtolower(trim($text));

        return $this->matchesMenuOption($normalized, 1) || in_array($normalized, ['si', 'sí', 'confirmar', 'guardar', 'aceptar', 'ok'], true);
    }

    private function isNegativeCommand(string $text): bool
    {
        $normalized = mb_strtolower(trim($text));

        return $this->matchesMenuOption($normalized, 2) || in_array($normalized, ['no', 'cancelar', 'cancel'], true);
    }

    private function isAllowedChat(string $chatId): bool
    {
        $allowed = config('services.telegram.allowed_chat_ids', []);

        if (!is_array($allowed) || $allowed === []) {
            return true;
        }

        return in_array($chatId, $allowed, true);
    }

    private function isHelpMessage(string $text): bool
    {
        $normalized = mb_strtolower(trim($text));

        return in_array($normalized, ['/start', '/ayuda', '/menu', 'ayuda', 'help', 'menu'], true);
    }

    private function isCancelCommand(string $text): bool
    {
        $normalized = mb_strtolower(trim($text));

        return in_array($normalized, ['cancelar', 'cancel', '/cancelar', '/cancel'], true) ||
            $this->matchesMenuOption($normalized, 0);
    }

    private function isListCommand(string $text): bool
    {
        $normalized = mb_strtolower(trim($text));

        return in_array($normalized, ['/cotizaciones', 'cotizaciones', 'listar cotizaciones', 'lista de cotizaciones', 'listado de cotizaciones', 'ver cotizaciones', 'listar facturas'], true) ||
            $this->matchesMenuOption($normalized, 2);
    }

    private function isAddPaymentCommand(string $text): bool
    {
        $normalized = mb_strtolower(trim($text));

        return $this->matchesMenuOption($normalized, 4) ||
            str_contains($normalized, 'anticipo') ||
            str_contains($normalized, 'registrar pago') ||
            str_contains($normalized, 'agregar pago');
    }

    private function isSendPdfCommand(string $text): bool
    {
        $normalized = mb_strtolower(trim($text));

        return $this->matchesMenuOption($normalized, 3) ||
            str_contains($normalized, 'enviar pdf') ||
            str_contains($normalized, 'reenviar pdf') ||
            str_contains($normalized, 'mandar pdf') ||
            str_contains($normalized, 'descargar pdf');
    }

    private function isEditCommand(string $text): bool
    {
        $normalized = mb_strtolower(trim($text));

        return $this->matchesMenuOption($normalized, 5) ||
            str_contains($normalized, 'editar cotizacion') ||
            str_contains($normalized, 'editar cotización') ||
            str_contains($normalized, 'editar factura') ||
            $normalized === 'editar';
    }

    private function isFinishEditCommand(string $text): bool
    {
        $normalized = mb_strtolower(trim($text));

        return in_array($normalized, ['finalizar', 'terminar', 'listo', 'hecho'], true) ||
            $this->matchesMenuOption($normalized, 9);
    }

    private function helpMessage(): string
    {
        return "Bot de cotizaciones activo.\n".
            "Escribe el número de la opción que necesites.\n\n".
            "Tip: puedes escribir cancelar en cualquier momento para detener el flujo actual.";
    }

}

