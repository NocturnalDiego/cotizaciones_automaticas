<?php

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

test('telegram bot shows main menu with keyboard buttons on start', function () {
    [$service, $telegramClient] = botServiceFixture();
    authorizeChatForBotTest(100);

    $service->processUpdate([
        'message' => [
            'chat' => ['id' => 100],
            'text' => '/start',
        ],
    ]);

    expect($telegramClient->messages)->toHaveCount(1);
    expect($telegramClient->messages[0])->toContain('Selecciona una opción en el teclado');
    expect($telegramClient->messagePayloads[0]['reply_markup'])->not->toBeNull();
    expect((string) data_get($telegramClient->messagePayloads[0], 'reply_markup.keyboard.0.0.text'))->toBe('1 Crear cotización');
});

test('telegram bot rejects free text creation and keeps guided entry only', function () {
    [$service, $telegramClient] = botServiceFixture();
    authorizeChatForBotTest(110);

    $service->processUpdate([
        'message' => [
            'chat' => ['id' => 110],
            'text' => 'Crea una cotización para cliente Nutec con una partida de prueba',
        ],
    ]);

    expect($telegramClient->messages)->toHaveCount(1);
    expect($telegramClient->messages[0])->toContain('solo en modo guiado');
    expect((string) data_get($telegramClient->messagePayloads[0], 'reply_markup.keyboard.0.0.text'))->toBe('1 Crear cotización');
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
    expect((string) data_get($telegramClient->messagePayloads[0], 'reply_markup.keyboard.0.0.text'))->toBe('9 Buscar');
    expect((string) data_get($telegramClient->messagePayloads[0], 'reply_markup.keyboard.1.0.text'))->toBe('3 Reenviar PDF');
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
    expect((string) data_get($telegramClient->messagePayloads[0], 'reply_markup.keyboard.0.0.text'))->toBe('1');
    expect((string) data_get($telegramClient->messagePayloads[1], 'reply_markup.keyboard.0.0.text'))->toBe('9 Ver formato');
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
    expect((string) data_get($telegramClient->messagePayloads[1], 'reply_markup.keyboard.0.0.text'))->toBe('1 Cliente');
    expect((string) data_get($telegramClient->messagePayloads[2], 'reply_markup.keyboard.0.0.text'))->toBe('vacio');
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
    expect($telegramClient->messages[1])->toContain('Te envío el PDF de COT-000004');
    expect($telegramClient->documents)->toHaveCount(1);
    expect($telegramClient->documents[0]['caption'])->toBe('PDF de COT-000004');
    expect((string) data_get($telegramClient->messagePayloads[0], 'reply_markup.keyboard.0.0.text'))->toBe('1');
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
    $service->processUpdate(['message' => ['chat' => ['id' => 108], 'text' => 'Contacto de Prueba']]);
    $service->processUpdate(['message' => ['chat' => ['id' => 108], 'text' => 'oscar@example.com']]);
    $service->processUpdate(['message' => ['chat' => ['id' => 108], 'text' => '5511223344']]);
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
    expect((string) data_get($telegramClient->messagePayloads[0], 'reply_markup.keyboard.2.0.text'))->toBe('8 Siguiente');

    $service->processUpdate([
        'message' => [
            'chat' => ['id' => 109],
            'text' => '8 Siguiente',
        ],
    ]);

    expect($telegramClient->messages[1])->toContain('página 2 de 2');
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
    expect($telegramClient->messages[0])->toContain('Te envío el PDF de COT-000005');
    expect($telegramClient->documents)->toHaveCount(1);
    expect($telegramClient->documents[0]['caption'])->toBe('PDF de COT-000005');
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
