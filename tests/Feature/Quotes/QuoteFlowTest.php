<?php

use App\Models\Quote;
use App\Models\User;
use App\Support\AppPermissions;
use Spatie\Permission\Models\Permission;
use function Pest\Laravel\actingAs;

function grantQuotePermissions(User $user, array $permissions): void
{
    foreach ($permissions as $permission) {
        Permission::findOrCreate($permission, 'web');
    }

    $user->syncPermissions($permissions);
}

test('authenticated user can create a quote and calculate totals', function () {
    $user = User::factory()->create();
    grantQuotePermissions($user, [AppPermissions::QUOTES_CREATE, AppPermissions::QUOTES_VIEW]);

    $response = actingAs($user)
        ->post(route('cotizaciones.store'), [
            'reference_code' => '4K097',
            'client_name' => 'Nutec',
            'client_rfc' => 'NUT000000AAA',
            'location' => 'Tecámac, Edo. México',
            'issued_at' => '2026-03-28',
            'terms' => 'Estos costos se respetarán siempre y cuando se cuente con área y materiales disponibles.',
            'contact_phone' => '5510000000',
            'contact_email' => 'contacto.pruebas@example.com',
            'contact_name' => 'Contacto de Prueba',
            'item_description' => [
                'Configuración de 1272 posiciones con crossbar pijado, eliminando un nivel.',
                'Cortes de 74 protectores (retoque)',
            ],
            'item_quantity' => [1272, 74],
            'item_unit_price' => [90, 108.108108],
        ]);

    $quote = Quote::query()->first();

    expect($quote)->not->toBeNull();

    $response->assertRedirect(route('cotizaciones.view', $quote));

    $quote->refresh();

    expect($quote->folio)->toStartWith('COT-');
    expect((float) $quote->subtotal)->toBe(122480.00);
    expect((float) $quote->vat_amount)->toBe(0.00);
    expect((float) $quote->total)->toBe(122480.00);
    expect((float) $quote->balance_due)->toBe(122480.00);
    expect($quote->status)->toBe('emitida');
});

test('authenticated user can create a quote without client name', function () {
    $user = User::factory()->create();
    grantQuotePermissions($user, [AppPermissions::QUOTES_CREATE, AppPermissions::QUOTES_VIEW]);

    $response = actingAs($user)
        ->post(route('cotizaciones.store'), [
            'reference_code' => '4K120',
            'client_name' => '',
            'issued_at' => '2026-03-28',
            'terms' => 'Condiciones de ejemplo',
            'item_description' => [
                'Partida sin cliente',
            ],
            'item_quantity' => [2],
            'item_unit_price' => [1500],
        ]);

    $quote = Quote::query()->first();

    expect($quote)->not->toBeNull();

    $response->assertRedirect(route('cotizaciones.view', $quote));

    $quote->refresh();

    expect($quote->client_name)->toBe('');
    expect((float) $quote->total)->toBe(3000.00);
});

test('user can register anticipo and update quote balance', function () {
    $user = User::factory()->create();
    grantQuotePermissions($user, [AppPermissions::QUOTES_EDIT, AppPermissions::QUOTES_VIEW]);

    $quote = Quote::create([
        'folio' => 'COT-000100',
        'reference_code' => '4J091',
        'client_name' => 'Bridgestone',
        'issued_at' => now()->toDateString(),
        'vat_rate' => 0,
        'terms' => 'Condiciones de ejemplo',
    ]);

    $quote->items()->create([
        'description' => 'Servicio de montaje',
        'quantity' => 1,
        'unit_price' => 44000,
        'line_total' => 44000,
        'position' => 1,
    ]);

    $quote->recalculateTotals();

    $response = actingAs($user)
        ->post(route('cotizaciones.anticipos.store', $quote), [
            'label' => 'Primer anticipo',
            'amount' => 10000,
            'received_at' => now()->toDateString(),
            'notes' => 'Pago recibido por transferencia',
        ]);

    $response->assertRedirect(route('cotizaciones.view', $quote));

    $quote->refresh();

    expect((float) $quote->total)->toBe(44000.00);
    expect((float) $quote->paid_total)->toBe(10000.00);
    expect((float) $quote->balance_due)->toBe(34000.00);
    expect($quote->status)->toBe('con_anticipo');
    expect($quote->payments()->count())->toBe(1);
});

test('user can edit anticipo and quote totals are recalculated', function () {
    $user = User::factory()->create();
    grantQuotePermissions($user, [AppPermissions::QUOTES_EDIT, AppPermissions::QUOTES_VIEW]);

    $quote = Quote::create([
        'folio' => 'COT-000101',
        'reference_code' => '4J092',
        'client_name' => 'Bridgestone',
        'issued_at' => now()->toDateString(),
        'vat_rate' => 0,
        'terms' => 'Condiciones de ejemplo',
    ]);

    $quote->items()->create([
        'description' => 'Servicio de montaje',
        'quantity' => 1,
        'unit_price' => 44000,
        'line_total' => 44000,
        'position' => 1,
    ]);

    $quote->payments()->create([
        'label' => 'Primer anticipo',
        'amount' => 10000,
        'received_at' => now()->toDateString(),
        'notes' => 'Pago inicial',
    ]);

    $quote->recalculateTotals();

    $payment = $quote->payments()->first();

    $response = actingAs($user)
        ->put(route('cotizaciones.anticipos.update', [$quote, $payment]), [
            'label' => 'Anticipo ajustado',
            'amount' => 15000,
            'received_at' => now()->toDateString(),
            'notes' => 'Monto corregido',
        ]);

    $response->assertRedirect(route('cotizaciones.view', $quote));

    $quote->refresh();
    $payment->refresh();

    expect($payment->label)->toBe('Anticipo ajustado');
    expect((float) $payment->amount)->toBe(15000.00);
    expect((float) $quote->paid_total)->toBe(15000.00);
    expect((float) $quote->balance_due)->toBe(29000.00);
    expect($quote->status)->toBe('con_anticipo');
});

test('user can delete anticipo and quote totals are recalculated', function () {
    $user = User::factory()->create();
    grantQuotePermissions($user, [AppPermissions::QUOTES_EDIT, AppPermissions::QUOTES_VIEW]);

    $quote = Quote::create([
        'folio' => 'COT-000102',
        'reference_code' => '4J093',
        'client_name' => 'Bridgestone',
        'issued_at' => now()->toDateString(),
        'vat_rate' => 0,
        'terms' => 'Condiciones de ejemplo',
    ]);

    $quote->items()->create([
        'description' => 'Servicio de montaje',
        'quantity' => 1,
        'unit_price' => 44000,
        'line_total' => 44000,
        'position' => 1,
    ]);

    $quote->payments()->create([
        'label' => 'Primer anticipo',
        'amount' => 10000,
        'received_at' => now()->toDateString(),
        'notes' => 'Pago inicial',
    ]);

    $quote->recalculateTotals();

    $payment = $quote->payments()->first();

    $response = actingAs($user)
        ->delete(route('cotizaciones.anticipos.destroy', [$quote, $payment]));

    $response->assertRedirect(route('cotizaciones.view', $quote));

    $quote->refresh();

    expect($quote->payments()->count())->toBe(0);
    expect((float) $quote->paid_total)->toBe(0.00);
    expect((float) $quote->balance_due)->toBe(44000.00);
    expect($quote->status)->toBe('emitida');
});

test('authenticated user can download quote pdf', function () {
    $user = User::factory()->create();
    grantQuotePermissions($user, [AppPermissions::QUOTES_VIEW]);

    $quote = Quote::create([
        'folio' => 'COT-000200',
        'reference_code' => '4K099',
        'client_name' => 'Scania',
        'issued_at' => now()->toDateString(),
        'vat_rate' => 0,
        'terms' => 'Estos costos se respetarán siempre y cuando se cuente con área y materiales disponibles.',
    ]);

    $quote->items()->create([
        'description' => 'Armado de sistema selectivo',
        'quantity' => 1,
        'unit_price' => 316800,
        'line_total' => 316800,
        'position' => 1,
    ]);

    $quote->recalculateTotals();

    $response = actingAs($user)
        ->get(route('cotizaciones.pdf', $quote));

    $response->assertOk();
    expect((string) $response->headers->get('content-type'))->toContain('application/pdf');
});
