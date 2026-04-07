<?php

use App\Models\Quote;

test('pdf file name uses client and reference code when both exist', function () {
    $quote = new Quote([
        'folio' => 'COT-000200',
        'client_name' => 'Scania',
        'reference_code' => '4K099',
    ]);

    expect($quote->pdfFileBaseName())->toBe('Scania - 4K099');
});

test('pdf file name uses only client when reference code is empty', function () {
    $quote = new Quote([
        'folio' => 'COT-000201',
        'client_name' => 'Scania',
        'reference_code' => '',
    ]);

    expect($quote->pdfFileBaseName())->toBe('Scania');
});

test('pdf file name uses only reference code when client is empty', function () {
    $quote = new Quote([
        'folio' => 'COT-000202',
        'client_name' => '',
        'reference_code' => '4K100',
    ]);

    expect($quote->pdfFileBaseName())->toBe('4K100');
});

test('pdf file name falls back to folio when client and reference code are empty', function () {
    $quote = new Quote([
        'folio' => 'COT-000203',
        'client_name' => '',
        'reference_code' => '',
    ]);

    expect($quote->pdfFileBaseName())->toBe('COT-000203');
});
