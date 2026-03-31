<?php

use App\Models\Quote;
use App\Models\User;
use function Pest\Laravel\actingAs;

test('dashboard muestra metricas operativas reales', function () {
    $user = User::factory()->create();

    $emitida = Quote::create([
        'folio' => 'COT-000301',
        'reference_code' => '4K301',
        'client_name' => 'Cliente Uno',
        'issued_at' => now()->subDays(12)->toDateString(),
        'vat_rate' => 0,
        'terms' => 'Condiciones de ejemplo',
    ]);

    $emitida->items()->create([
        'description' => 'Servicio inicial',
        'quantity' => 1,
        'unit_price' => 10000,
        'line_total' => 10000,
        'position' => 1,
    ]);
    $emitida->recalculateTotals();

    $conAnticipo = Quote::create([
        'folio' => 'COT-000302',
        'reference_code' => '4K302',
        'client_name' => 'Cliente Dos',
        'issued_at' => now()->subDays(8)->toDateString(),
        'vat_rate' => 0,
        'terms' => 'Condiciones de ejemplo',
    ]);

    $conAnticipo->items()->create([
        'description' => 'Proyecto parcial',
        'quantity' => 1,
        'unit_price' => 20000,
        'line_total' => 20000,
        'position' => 1,
    ]);

    $conAnticipo->payments()->create([
        'label' => 'Anticipo',
        'amount' => 5000,
        'received_at' => now()->toDateString(),
        'notes' => 'Pago parcial',
    ]);
    $conAnticipo->recalculateTotals();

    $pagada = Quote::create([
        'folio' => 'COT-000303',
        'reference_code' => '4K303',
        'client_name' => 'Cliente Tres',
        'issued_at' => now()->subDays(3)->toDateString(),
        'vat_rate' => 0,
        'terms' => 'Condiciones de ejemplo',
    ]);

    $pagada->items()->create([
        'description' => 'Proyecto cerrado',
        'quantity' => 1,
        'unit_price' => 30000,
        'line_total' => 30000,
        'position' => 1,
    ]);

    $pagada->payments()->create([
        'label' => 'Pago total',
        'amount' => 30000,
        'received_at' => now()->toDateString(),
        'notes' => 'Liquidación',
    ]);
    $pagada->recalculateTotals();

    $response = actingAs($user)
        ->get(route('dashboard'));

    $response
        ->assertOk()
        ->assertSeeText('Panel operativo')
        ->assertViewHas('kpis', function (array $kpis): bool {
            return $kpis['total_quotes'] === 3
                && $kpis['quotes_this_month'] === 3
                && $kpis['open_quotes'] === 2
                && (float) $kpis['pending_amount'] === 25000.0
                && (float) $kpis['collected_this_month'] === 35000.0
                && (float) $kpis['average_ticket'] === 20000.0
                && (float) $kpis['paid_rate'] === 33.3;
        })
        ->assertViewHas('priorityCollectionQuotes', fn ($quotes): bool => $quotes->count() === 2)
        ->assertViewHas('statusSummary', function (array $summary): bool {
            $totals = collect($summary)->mapWithKeys(fn (array $item): array => [$item['key'] => $item['total']]);

            return ($totals[Quote::STATUS_EMITIDA] ?? 0) === 1
                && ($totals[Quote::STATUS_CON_ANTICIPO] ?? 0) === 1
                && ($totals[Quote::STATUS_PAGADA] ?? 0) === 1;
        });
});

test('dashboard muestra estado vacio sin cotizaciones', function () {
    $user = User::factory()->create();

    actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSeeText('Todavía no hay cotizaciones registradas.');
});
