<?php

use App\Models\AppSetting;
use App\Models\User;
use App\Support\AppPermissions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use function Pest\Laravel\actingAs;

function grantBrandingPermission(User $user): void
{
    Permission::findOrCreate(AppPermissions::BRANDING_MANAGE, 'web');
    $user->syncPermissions([AppPermissions::BRANDING_MANAGE]);
}

test('pagina de identidad de marca se muestra para usuario autenticado', function () {
    $user = User::factory()->create();
    grantBrandingPermission($user);

    actingAs($user)
        ->get(route('branding.edit'))
        ->assertOk()
        ->assertSeeText('Identidad de marca');
});

test('usuario autenticado puede actualizar identidad de marca y subir logotipo', function () {
    Storage::fake('public');

    $user = User::factory()->create();
    grantBrandingPermission($user);

    $response = actingAs($user)->patch(route('branding.update'), [
        'quote_brand_name' => 'Marca Principal',
        'issuer_name' => 'Empresa de Prueba',
        'issuer_rfc' => 'AAA010101AAA',
        'issuer_business_name' => 'Comercial Prueba SA de CV',
        'brand_logo' => UploadedFile::fake()->image('logo.png', 400, 400),
    ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('branding.edit'));

    $settings = AppSetting::query()->first();

    expect($settings)->not->toBeNull();
    expect($settings->quote_brand_name)->toBe('Marca Principal');
    expect($settings->issuer_name)->toBe('Empresa de Prueba');
    expect($settings->issuer_rfc)->toBe('AAA010101AAA');
    expect($settings->issuer_business_name)->toBe('Comercial Prueba SA de CV');
    expect($settings->brand_logo_path)->not->toBeNull();
    expect(Storage::disk('public')->exists((string) $settings->brand_logo_path))->toBeTrue();
});

test('usuario autenticado puede quitar logotipo global', function () {
    Storage::fake('public');

    $user = User::factory()->create();
    grantBrandingPermission($user);
    $path = UploadedFile::fake()->image('logo-previo.png', 300, 300)->store('branding', 'public');

    $settings = AppSetting::query()->create([
        'issuer_name' => 'Empresa',
        'issuer_rfc' => 'AAA010101AAA',
        'issuer_business_name' => 'Empresa SA de CV',
        'brand_logo_path' => $path,
    ]);

    actingAs($user)
        ->patch(route('branding.update'), [
            'quote_brand_name' => 'Marca Principal',
            'issuer_name' => 'Empresa',
            'issuer_rfc' => 'AAA010101AAA',
            'issuer_business_name' => 'Empresa SA de CV',
            'remove_logo' => '1',
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('branding.edit'));

    $settings->refresh();

    expect($settings->brand_logo_path)->toBeNull();
    expect(Storage::disk('public')->exists($path))->toBeFalse();
});
