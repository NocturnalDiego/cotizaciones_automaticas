<?php

use App\Models\Contact;
use App\Models\Quote;
use App\Models\User;
use App\Services\Quotes\QuoteAutomationService;
use App\Services\Telegram\TelegramClient;
use App\Services\Telegram\TelegramQuoteBotService;
use App\Services\Telegram\TelegramUserLinkService;
use App\Support\AppPermissions;
use Spatie\Permission\Models\Permission;

class FakeTelegramClientForBotTest extends TelegramClient
{
    /**
     * @var array<int, string>
     */
    public array $messages = [];

    /**
     * @var array<int, array{text:string,reply_markup:array<string, mixed>|null}>
     */
    public array $messagePayloads = [];

    /**
     * @var array<int, array{chat_id:int|string,file_path:string,caption:?string}>
     */
    public array $documents = [];

    /**
     * @var array<int, string>
     */
    public array $answeredCallbacks = [];

    public function __construct()
    {
    }

    public function sendMessage(int|string $chatId, string $text): void
    {
        $this->messages[] = $text;
        $this->messagePayloads[] = [
            'text' => $text,
            'reply_markup' => null,
        ];
    }

    /**
     * @param  array<string, mixed>|null  $replyMarkup
     */
    public function sendMessageWithMarkup(int|string $chatId, string $text, ?array $replyMarkup = null): void
    {
        $this->messages[] = $text;
        $this->messagePayloads[] = [
            'text' => $text,
            'reply_markup' => $replyMarkup,
        ];
    }

    public function sendDocument(int|string $chatId, string $filePath, ?string $caption = null): void
    {
        $this->documents[] = [
            'chat_id' => $chatId,
            'file_path' => $filePath,
            'caption' => $caption,
        ];
    }

    public function answerCallbackQuery(string $callbackQueryId, ?string $text = null): void
    {
        $this->answeredCallbacks[] = $callbackQueryId;
    }
}

class FakeQuoteAutomationServiceForBotTest extends QuoteAutomationService
{
    /**
     * @param  array<string, mixed>  $quoteData
     */
    public function createFromStructuredData(array $quoteData): Quote
    {
        return Quote::query()->firstOrFail();
    }

    public function buildPdfForQuote(Quote $quote): string
    {
        return storage_path('app/private/telegram/fake-'.$quote->id.'.pdf');
    }
}

function botServiceFixture(): array
{
    $telegramClient = new FakeTelegramClientForBotTest();
    $automation = new FakeQuoteAutomationServiceForBotTest();
    $linkService = new TelegramUserLinkService();

    $service = new TelegramQuoteBotService(
        $telegramClient,
        $automation,
        $linkService,
    );

    return [$service, $telegramClient];
}

function authorizeChatForBotTest(int|string $chatId, ?array $permissions = null): User
{
    foreach (AppPermissions::all() as $permissionName) {
        Permission::findOrCreate($permissionName, 'web');
    }

    $user = User::factory()->create([
        'telegram_chat_id' => (string) $chatId,
        'telegram_linked_at' => now(),
    ]);

    $user->syncPermissions($permissions ?? AppPermissions::all());

    return $user;
}

test('telegram bot requires account link before processing menu actions', function () {
    [$service, $telegramClient] = botServiceFixture();

    $service->processUpdate([
        'message' => [
            'chat' => ['id' => 130],
            'text' => '/start',
        ],
    ]);

    expect($telegramClient->messages)->toHaveCount(1);
    expect($telegramClient->messages[0])->toContain('no está vinculado a una cuenta del sistema');
    expect($telegramClient->messages[0])->toContain('/vincular CODIGO');
});

test('telegram bot can link chat with temporary code generated from web user', function () {
    $user = User::factory()->create();
    $linkService = new TelegramUserLinkService();
    $generated = $linkService->generateLinkCode($user);

    [$service, $telegramClient] = botServiceFixture();

    $service->processUpdate([
        'message' => [
            'chat' => ['id' => 131],
            'text' => '/vincular '.$generated['code'],
        ],
    ]);

    $user->refresh();

    expect($user->telegram_chat_id)->toBe('131');
    expect($telegramClient->messages[0])->toContain('Vinculación completada');
});

test('telegram bot denies edit actions when linked user lacks permission', function () {
    [$service, $telegramClient] = botServiceFixture();
    authorizeChatForBotTest(132, [AppPermissions::QUOTES_VIEW]);

    $service->processUpdate([
        'message' => [
            'chat' => ['id' => 132],
            'text' => 'agregar anticipo',
        ],
    ]);

    expect($telegramClient->messages[0])->toContain('no tiene permisos para ejecutar esta acción');
});

test('telegram bot shows main menu options as message on start', function () {
    [$service, $telegramClient] = botServiceFixture();
    authorizeChatForBotTest(100);

    $service->processUpdate([
        'message' => [
            'chat' => ['id' => 100],
            'text' => '/start',
        ],
    ]);

    expect($telegramClient->messages)->toHaveCount(1);
    expect($telegramClient->messages[0])->toContain('Escribe una opción del menú');
    expect((string) data_get($telegramClient->messagePayloads[0], 'reply_markup.inline_keyboard.0.0.text'))->toBe('1 Crear cotización');
    expect((string) data_get($telegramClient->messagePayloads[0], 'reply_markup.inline_keyboard.0.1.text'))->toBe('2 Listar cotizaciones');
});

test('telegram bot sends incomplete free text to guided flow with detected context', function () {
    [$service, $telegramClient] = botServiceFixture();
    authorizeChatForBotTest(110);

    app()->instance(\App\Services\Ai\QuoteInstructionParser::class, new class {
        public function parse(string $message): array
        {
            return [
                'can_create' => false,
                'reason' => 'Falta precio unitario para completar la cotización.',
                'quote' => [
                    'reference_code' => '4K110',
                    'client_name' => 'Nutec',
                    'client_rfc' => '',
                    'location' => 'Tecámac',
                    'issued_at' => now()->toDateString(),
                    'terms' => '',
                    'contact_name' => '',
                    'contact_email' => '',
                    'contact_phone' => '',
                    'items' => [],
                ],
            ];
        }
    });

    $service->processUpdate([
        'message' => [
            'chat' => ['id' => 110],
            'text' => 'Crea una cotización para cliente Nutec con una partida de prueba',
        ],
    ]);

    expect($telegramClient->messages)->toHaveCount(1);
    expect($telegramClient->messages[0])->toContain('Falta precio unitario para completar la cotización.');
    expect($telegramClient->messages[0])->toContain('Creación guiada de cotización.');
    expect($telegramClient->messages[0])->toContain('Paso 4 de 7: escribe la descripción del concepto.');
    expect($telegramClient->messagePayloads[0]['reply_markup'])->toBeNull();
});

test('telegram bot can create quote from free text when parser returns complete data', function () {
    Quote::create([
        'folio' => 'COT-000111',
        'reference_code' => 'BASE-LIBRE',
        'client_name' => 'Base Libre',
        'issued_at' => now()->toDateString(),
        'vat_rate' => 0,
        'total' => 1000,
    ]);

    [$service, $telegramClient] = botServiceFixture();
    authorizeChatForBotTest(111);

    app()->instance(\App\Services\Ai\QuoteInstructionParser::class, new class {
        public function parse(string $message): array
        {
            return [
                'can_create' => true,
                'reason' => '',
                'quote' => [
                    'reference_code' => 'PROY-111',
                    'client_name' => 'Cliente Libre',
                    'client_rfc' => '',
                    'location' => 'CDMX',
                    'issued_at' => now()->toDateString(),
                    'terms' => '',
                    'contact_name' => '',
                    'contact_email' => '',
                    'contact_phone' => '',
                    'items' => [
                        [
                            'description' => 'Instalación',
                            'quantity' => 1,
                            'unit_price' => 1500,
                        ],
                    ],
                ],
            ];
        }
    });

    $service->processUpdate([
        'message' => [
            'chat' => ['id' => 111],
            'text' => 'Cotiza algo para cliente libre',
        ],
    ]);

    expect($telegramClient->messages)->toHaveCount(1);
    expect($telegramClient->messages[0])->toContain('Cotización creada correctamente.');
    expect($telegramClient->documents)->toHaveCount(1);
});

test('telegram bot can list quotes with cliente, proyecto y total', function () {
    Quote::create([
        'folio' => 'COT-000001',
        'reference_code' => '4K097',
        'client_name' => 'Nutec',
        'issued_at' => now()->toDateString(),
        'vat_rate' => 0,
        'total' => 122480,
    ]);

    [$service, $telegramClient] = botServiceFixture();
    authorizeChatForBotTest(101);

    $service->processUpdate([
        'message' => [
            'chat' => ['id' => 101],
            'text' => 'listar cotizaciones',
        ],
    ]);

    expect($telegramClient->messages)->toHaveCount(1);
    expect($telegramClient->messages[0])->toContain('Listado de cotizaciones (página 1 de 1):');
    expect($telegramClient->messages[0])->toContain('Cliente: Nutec');
    expect($telegramClient->messages[0])->toContain('Proyecto: 4K097');
    expect($telegramClient->messages[0])->toContain('$122,480.00 + IVA');
    expect((string) data_get($telegramClient->messagePayloads[0], 'reply_markup.inline_keyboard.0.0.text'))->toBe('9 Buscar');
    expect((string) data_get($telegramClient->messagePayloads[0], 'reply_markup.inline_keyboard.1.0.text'))->toBe('3 Reenviar PDF');
    expect((string) data_get($telegramClient->messagePayloads[0], 'reply_markup.inline_keyboard.1.1.text'))->toBe('4 Agregar anticipo');
});

test('telegram bot can guide anticipo flow and update quote totals', function () {
    $quote = Quote::create([
        'folio' => 'COT-000002',
        'reference_code' => '4K098',
        'client_name' => 'Scania',
        'issued_at' => now()->toDateString(),
        'vat_rate' => 0,
    ]);

    $quote->items()->create([
        'description' => 'Servicio de montaje',
        'quantity' => 1,
        'unit_price' => 1000,
        'line_total' => 1000,
        'position' => 1,
    ]);

    $quote->recalculateTotals();

    [$service, $telegramClient] = botServiceFixture();
    authorizeChatForBotTest(102);

    $service->processUpdate([
        'message' => [
            'chat' => ['id' => 102],
            'text' => 'agregar anticipo',
        ],
    ]);

    $service->processUpdate([
        'message' => [
            'chat' => ['id' => 102],
            'text' => $quote->folio,
        ],
    ]);

    $service->processUpdate([
        'message' => [
            'chat' => ['id' => 102],
            'text' => 'monto 200, concepto Primer anticipo, fecha 2026-03-28, notas Transferencia',
        ],
    ]);

    $quote->refresh();

    expect((float) $quote->paid_total)->toBe(200.00);
    expect((float) $quote->balance_due)->toBe(800.00);
    expect($quote->payments()->count())->toBe(1);
    expect(collect($telegramClient->messages)->last())->toContain('Anticipo registrado correctamente');
    expect($telegramClient->documents)->toHaveCount(1);
    expect((string) data_get($telegramClient->messagePayloads[0], 'reply_markup.inline_keyboard.0.0.text'))->toBe('1');
    expect((string) data_get($telegramClient->messagePayloads[1], 'reply_markup.inline_keyboard.0.0.text'))->toBe('9 Ver formato');
});

test('telegram bot can select quote number 6 in anticipo flow without triggering help', function () {
    for ($index = 1; $index <= 6; $index++) {
        $quote = Quote::create([
            'folio' => 'COT-20000'.$index,
            'reference_code' => 'ANT-'.$index,
            'client_name' => 'Cliente '.$index,
            'issued_at' => now()->toDateString(),
            'vat_rate' => 0,
        ]);

        $quote->items()->create([
            'description' => 'Servicio '.$index,
            'quantity' => 1,
            'unit_price' => 1000,
            'line_total' => 1000,
            'position' => 1,
        ]);

        $quote->recalculateTotals();
    }

    [$service, $telegramClient] = botServiceFixture();
    authorizeChatForBotTest(120);

    $service->processUpdate([
        'message' => [
            'chat' => ['id' => 120],
            'text' => 'agregar anticipo',
        ],
    ]);

    $service->processUpdate([
        'message' => [
            'chat' => ['id' => 120],
            'text' => '6',
        ],
    ]);

    expect($telegramClient->messages[1])->toContain('Seleccionaste');
    expect($telegramClient->messages[1])->not->toContain('Bot de cotizaciones activo');
});

test('telegram bot can guide quote edit flow for client name', function () {
    $quote = Quote::create([
        'folio' => 'COT-000003',
        'reference_code' => '4K099',
        'client_name' => 'Cliente Inicial',
        'issued_at' => now()->toDateString(),
        'vat_rate' => 0,
    ]);

    [$service, $telegramClient] = botServiceFixture();
    authorizeChatForBotTest(103);

    $service->processUpdate([
        'message' => [
            'chat' => ['id' => 103],
            'text' => 'editar factura',
        ],
    ]);

    $service->processUpdate([
        'message' => [
            'chat' => ['id' => 103],
            'text' => $quote->folio,
        ],
    ]);

    $service->processUpdate([
        'message' => [
            'chat' => ['id' => 103],
            'text' => '1',
        ],
    ]);

    $service->processUpdate([
        'message' => [
            'chat' => ['id' => 103],
            'text' => 'Cliente Editado SA de CV',
        ],
    ]);

    $quote->refresh();

    expect($quote->client_name)->toBe('Cliente Editado SA de CV');
    expect(collect($telegramClient->messages)->last())->toContain('Campo actualizado: Cliente');
    expect((string) data_get($telegramClient->messagePayloads[1], 'reply_markup.inline_keyboard.0.0.text'))->toBe('1 Cliente');
    expect((string) data_get($telegramClient->messagePayloads[2], 'reply_markup.inline_keyboard.0.0.text'))->toBe('vacio');
});

test('telegram bot can update quote contact from catalog in edit flow', function () {
    $quote = Quote::create([
        'folio' => 'COT-000031',
        'reference_code' => '4K131',
        'client_name' => 'Cliente Contacto',
        'issued_at' => now()->toDateString(),
        'vat_rate' => 0,
    ]);

    Contact::query()->create([
        'name' => 'Contacto Catálogo',
        'email' => 'catalogo@example.com',
        'phone' => '5511998877',
    ]);

    [$service, $telegramClient] = botServiceFixture();
    authorizeChatForBotTest(133);

    $service->processUpdate([
        'message' => [
            'chat' => ['id' => 133],
            'text' => 'editar factura',
        ],
    ]);

    $service->processUpdate([
        'message' => [
            'chat' => ['id' => 133],
            'text' => $quote->folio,
        ],
    ]);

    $service->processUpdate([
        'message' => [
            'chat' => ['id' => 133],
            'text' => '6',
        ],
    ]);

    $service->processUpdate([
        'message' => [
            'chat' => ['id' => 133],
            'text' => '1',
        ],
    ]);

    $quote->refresh();

    expect($quote->contact_name)->toBe('Contacto Catálogo');
    expect($quote->contact_email)->toBe('catalogo@example.com');
    expect($quote->contact_phone)->toBe('5511998877');
    expect(collect($telegramClient->messages)->last())->toContain('Contacto actualizado: Contacto Catálogo.');
});

test('telegram bot can guide resend pdf flow from listed quotes', function () {
    $quote = Quote::create([
        'folio' => 'COT-000004',
        'reference_code' => '4K100',
        'client_name' => 'Cliente PDF',
        'issued_at' => now()->toDateString(),
        'vat_rate' => 0,
        'total' => 5000,
    ]);

    [$service, $telegramClient] = botServiceFixture();
    authorizeChatForBotTest(104);

    $service->processUpdate([
        'message' => [
            'chat' => ['id' => 104],
            'text' => 'enviar pdf',
        ],
    ]);

    $service->processUpdate([
        'message' => [
            'chat' => ['id' => 104],
            'text' => $quote->folio,
        ],
    ]);

    expect($telegramClient->messages)->toHaveCount(2);
    expect($telegramClient->messages[0])->toContain('Selecciona la cotización de la cual deseas reenviar el PDF');
    expect($telegramClient->messages[1])->toContain('Te envío el PDF de Cliente PDF - 4K100');
    expect($telegramClient->documents)->toHaveCount(1);
    expect($telegramClient->documents[0]['caption'])->toBe('PDF de Cliente PDF - 4K100');
    expect((string) data_get($telegramClient->messagePayloads[0], 'reply_markup.inline_keyboard.0.0.text'))->toBe('1');
});

test('telegram bot can create quote in guided mode', function () {
    Quote::create([
        'folio' => 'COT-009999',
        'reference_code' => 'BASE-001',
        'client_name' => 'Base',
        'issued_at' => now()->toDateString(),
        'vat_rate' => 0,
        'total' => 1000,
    ]);

    Contact::query()->create([
        'name' => 'Contacto de Prueba',
        'email' => 'oscar@example.com',
        'phone' => '5511223344',
    ]);

    [$service, $telegramClient] = botServiceFixture();
    authorizeChatForBotTest(108);

    $service->processUpdate([
        'message' => [
            'chat' => ['id' => 108],
            'text' => '1 Crear cotización',
        ],
    ]);

    $service->processUpdate(['message' => ['chat' => ['id' => 108], 'text' => 'Cliente Guiado SA de CV']]);
    $service->processUpdate(['message' => ['chat' => ['id' => 108], 'text' => '4K500']]);
    $service->processUpdate(['message' => ['chat' => ['id' => 108], 'text' => 'Tecámac']]);
    $service->processUpdate(['message' => ['chat' => ['id' => 108], 'text' => 'Armado de estructura']]);
    $service->processUpdate(['message' => ['chat' => ['id' => 108], 'text' => '2']]);
    $service->processUpdate(['message' => ['chat' => ['id' => 108], 'text' => '1500']]);
    $service->processUpdate(['message' => ['chat' => ['id' => 108], 'text' => '2 No, continuar']]);
    $service->processUpdate(['message' => ['chat' => ['id' => 108], 'text' => '1']]);
    $service->processUpdate(['message' => ['chat' => ['id' => 108], 'text' => '1 Confirmar cotización']]);

    expect(collect($telegramClient->messages)->last())->toContain('Cotización creada correctamente.');
    expect($telegramClient->documents)->toHaveCount(1);
});

test('telegram bot paginates quote selection for send pdf flow', function () {
    for ($index = 1; $index <= 9; $index++) {
        Quote::create([
            'folio' => 'COT-10'.str_pad((string) $index, 4, '0', STR_PAD_LEFT),
            'reference_code' => 'REF-'.$index,
            'client_name' => 'Cliente '.$index,
            'issued_at' => now()->toDateString(),
            'vat_rate' => 0,
            'total' => 1000 + $index,
        ]);
    }

    [$service, $telegramClient] = botServiceFixture();
    authorizeChatForBotTest(109);

    $service->processUpdate([
        'message' => [
            'chat' => ['id' => 109],
            'text' => 'enviar pdf',
        ],
    ]);

    expect($telegramClient->messages[0])->toContain('página 1 de 2');
    expect((string) data_get($telegramClient->messagePayloads[0], 'reply_markup.inline_keyboard.2.0.text'))->toBe('8 Siguiente');

    $service->processUpdate([
        'message' => [
            'chat' => ['id' => 109],
            'text' => '8 Siguiente',
        ],
    ]);

    expect($telegramClient->messages[1])->toContain('página 2 de 2');
});

test('telegram bot processes inline callback data as command', function () {
    [$service, $telegramClient] = botServiceFixture();
    authorizeChatForBotTest(140);

    $service->processUpdate([
        'message' => [
            'chat' => ['id' => 140],
            'text' => '/start',
        ],
    ]);

    $service->processUpdate([
        'callback_query' => [
            'id' => 'cbq_140_menu_2',
            'data' => '2 Listar cotizaciones',
            'message' => [
                'chat' => ['id' => 140],
            ],
        ],
    ]);

    expect($telegramClient->answeredCallbacks)->toContain('cbq_140_menu_2');
    expect($telegramClient->messages[1])->toContain('No hay cotizaciones registradas todavía.');
});

test('telegram bot can resend pdf directly when folio is in same message', function () {
    $quote = Quote::create([
        'folio' => 'COT-000005',
        'reference_code' => '4K101',
        'client_name' => 'Cliente Directo PDF',
        'issued_at' => now()->toDateString(),
        'vat_rate' => 0,
        'total' => 7400,
    ]);

    [$service, $telegramClient] = botServiceFixture();
    authorizeChatForBotTest(105);

    $service->processUpdate([
        'message' => [
            'chat' => ['id' => 105],
            'text' => 'reenviar pdf COT-000005',
        ],
    ]);

    expect($telegramClient->messages)->toHaveCount(1);
    expect($telegramClient->messages[0])->toContain('Te envío el PDF de Cliente Directo PDF - 4K101');
    expect($telegramClient->documents)->toHaveCount(1);
    expect($telegramClient->documents[0]['caption'])->toBe('PDF de Cliente Directo PDF - 4K101');
});

test('telegram bot can parse natural amount like 50 mil in anticipo flow', function () {
    $quote = Quote::create([
        'folio' => 'COT-000006',
        'reference_code' => '4K102',
        'client_name' => 'Cliente Monto Natural',
        'issued_at' => now()->toDateString(),
        'vat_rate' => 0,
    ]);

    $quote->items()->create([
        'description' => 'Servicio de montaje',
        'quantity' => 1,
        'unit_price' => 120000,
        'line_total' => 120000,
        'position' => 1,
    ]);

    $quote->recalculateTotals();

    [$service] = botServiceFixture();
    authorizeChatForBotTest(106);

    $service->processUpdate([
        'message' => [
            'chat' => ['id' => 106],
            'text' => 'agregar anticipo',
        ],
    ]);

    $service->processUpdate([
        'message' => [
            'chat' => ['id' => 106],
            'text' => '1',
        ],
    ]);

    $service->processUpdate([
        'message' => [
            'chat' => ['id' => 106],
            'text' => 'monto 50 mil, concepto anticipo parcial',
        ],
    ]);

    $quote->refresh();

    expect((float) $quote->paid_total)->toBe(50000.00);
    expect((float) $quote->balance_due)->toBe(70000.00);
});

test('telegram bot can parse thousand separators and words in direct anticipo command', function () {
    $quote = Quote::create([
        'folio' => 'COT-000007',
        'reference_code' => '4K103',
        'client_name' => 'Cliente Separadores',
        'issued_at' => now()->toDateString(),
        'vat_rate' => 0,
    ]);

    $quote->items()->create([
        'description' => 'Servicio de montaje',
        'quantity' => 1,
        'unit_price' => 250000,
        'line_total' => 250000,
        'position' => 1,
    ]);

    $quote->recalculateTotals();

    [$service] = botServiceFixture();
    authorizeChatForBotTest(107);

    $service->processUpdate([
        'message' => [
            'chat' => ['id' => 107],
            'text' => 'agregar anticipo COT-000007 monto 100,000 concepto primer pago',
        ],
    ]);

    $quote->refresh();
    expect((float) $quote->paid_total)->toBe(100000.00);

    $service->processUpdate([
        'message' => [
            'chat' => ['id' => 107],
            'text' => 'agregar anticipo COT-000007 monto cien mil concepto segundo pago',
        ],
    ]);

    $quote->refresh();

    expect((float) $quote->paid_total)->toBe(200000.00);
    expect((float) $quote->balance_due)->toBe(50000.00);
});
