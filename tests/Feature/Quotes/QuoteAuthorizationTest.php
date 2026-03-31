<?php

use App\Models\Quote;
use App\Models\User;
use App\Support\AppPermissions;
use Spatie\Permission\Models\Permission;
use function Pest\Laravel\actingAs;

function syncQuotePermissions(User $user, array $permissions): void
{
    foreach ([
        AppPermissions::QUOTES_VIEW,
        AppPermissions::QUOTES_CREATE,
        AppPermissions::QUOTES_EDIT,
    ] as $permissionName) {
        Permission::findOrCreate($permissionName, 'web');
    }

    $user->syncPermissions($permissions);
}

test('usuario con permiso solo de ver no puede crear ni editar cotizaciones', function () {
    $user = User::factory()->create();
    syncQuotePermissions($user, [AppPermissions::QUOTES_VIEW]);

    $quote = Quote::create([
        'folio' => 'COT-000901',
        'reference_code' => 'REF-901',
        'client_name' => 'Cliente',
        'issued_at' => now()->toDateString(),
        'vat_rate' => 0,
        'terms' => 'Condiciones',
    ]);

    actingAs($user)->get(route('cotizaciones.index'))->assertOk();
    actingAs($user)->get(route('cotizaciones.view', $quote))->assertOk();

    actingAs($user)->get(route('cotizaciones.create'))->assertForbidden();
    actingAs($user)->post(route('cotizaciones.store'), [
        'issued_at' => now()->toDateString(),
        'item_description' => ['Partida'],
        'item_quantity' => [1],
        'item_unit_price' => [100],
    ])->assertForbidden();
    actingAs($user)->get(route('cotizaciones.edit', $quote))->assertForbidden();
});

test('usuario con permisos de ver y crear puede crear pero no editar', function () {
    $user = User::factory()->create();
    syncQuotePermissions($user, [AppPermissions::QUOTES_VIEW, AppPermissions::QUOTES_CREATE]);

    $createResponse = actingAs($user)->post(route('cotizaciones.store'), [
        'reference_code' => 'REF-902',
        'issued_at' => now()->toDateString(),
        'item_description' => ['Partida autorizada'],
        'item_quantity' => [1],
        'item_unit_price' => [500],
    ]);

    $createdQuote = Quote::query()->where('reference_code', 'REF-902')->first();

    expect($createdQuote)->not->toBeNull();
    $createResponse->assertRedirect(route('cotizaciones.view', $createdQuote));

    actingAs($user)->get(route('cotizaciones.edit', $createdQuote))->assertForbidden();
});
