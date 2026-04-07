<?php

use App\Services\Quotes\QuoteAutomationService;

test('quote automation service can create quote from structured data', function () {
    $service = app(QuoteAutomationService::class);

    $quote = $service->createFromStructuredData([
        'reference_code' => '4K097',
        'client_name' => 'Nutec',
        'client_rfc' => 'NUT000000AAA',
        'location' => 'Tecamac, Edo. Mexico',
        'issued_at' => '2026-03-28',
        'terms' => 'Estos costos se respetaran siempre y cuando se cuente con area libre y materiales disponibles.',
        'contact_phone' => '5510000000',
        'contact_email' => 'contacto.pruebas@example.com',
        'contact_name' => 'Contacto de Prueba',
        'items' => [
            [
                'description' => 'Configuracion de 1272 posiciones',
                'quantity' => 1272,
                'unit_price' => 90,
            ],
            [
                'description' => 'Cortes de protectores',
                'quantity' => 74,
                'unit_price' => 108.108108,
            ],
        ],
    ]);

    $quote->refresh();

    expect($quote->folio)->toStartWith('COT-');
    expect((float) $quote->subtotal)->toBe(122480.00);
    expect((float) $quote->vat_amount)->toBe(0.00);
    expect((float) $quote->total)->toBe(122480.00);
    expect((float) $quote->balance_due)->toBe(122480.00);
    expect($quote->items()->count())->toBe(2);
});

test('quote automation service builds pdf with platform file naming convention', function () {
    $service = app(QuoteAutomationService::class);

    $quote = $service->createFromStructuredData([
        'reference_code' => '4K700',
        'client_name' => 'Cliente Telegram',
        'issued_at' => '2026-04-06',
        'items' => [
            [
                'description' => 'Concepto de prueba',
                'quantity' => 1,
                'unit_price' => 1000,
            ],
        ],
    ]);

    $pdfPath = $service->buildPdfForQuote($quote);

    expect(basename($pdfPath))->toBe('Cliente Telegram - 4K700.pdf');

    if (is_file($pdfPath)) {
        @unlink($pdfPath);
    }
});
