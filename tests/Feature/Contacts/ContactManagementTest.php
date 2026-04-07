<?php

use App\Models\Contact;
use App\Models\User;
use App\Support\AppPermissions;
use Spatie\Permission\Models\Permission;
use function Pest\Laravel\actingAs;

function syncContactPermissions(User $user, array $permissions): void
{
    foreach (AppPermissions::all() as $permissionName) {
        Permission::findOrCreate($permissionName, 'web');
    }

    $user->syncPermissions($permissions);
}

test('usuario con permiso puede ver listado de contactos', function () {
    $user = User::factory()->create();
    syncContactPermissions($user, [AppPermissions::CONTACTS_VIEW]);

    Contact::query()->create([
        'name' => 'Contacto Comercial',
        'email' => 'contacto.comercial@example.com',
        'phone' => '5511223344',
    ]);

    actingAs($user)
        ->get(route('contactos.index'))
        ->assertOk()
        ->assertSee('Contacto Comercial');
});

test('usuario sin permiso no puede ver contactos', function () {
    $user = User::factory()->create();

    actingAs($user)
        ->get(route('contactos.index'))
        ->assertForbidden();
});

test('usuario con permiso de edicion puede crear y actualizar contactos', function () {
    $user = User::factory()->create();
    syncContactPermissions($user, [AppPermissions::CONTACTS_VIEW, AppPermissions::CONTACTS_EDIT]);

    $createResponse = actingAs($user)
        ->post(route('contactos.store'), [
            'name' => 'Contacto Operativo',
            'email' => 'contacto.operativo@example.com',
            'phone' => '5544556677',
        ]);

    $contact = Contact::query()->where('email', 'contacto.operativo@example.com')->first();

    expect($contact)->not->toBeNull();
    $createResponse->assertRedirect(route('contactos.view', $contact));

    $updateResponse = actingAs($user)
        ->put(route('contactos.update', $contact), [
            'name' => 'Contacto Operativo Editado',
            'email' => 'contacto.operativo@example.com',
            'phone' => '5599887766',
        ]);

    $updateResponse->assertRedirect(route('contactos.view', $contact));

    $contact->refresh();

    expect($contact->name)->toBe('Contacto Operativo Editado');
    expect($contact->phone)->toBe('5599887766');
});

test('usuario sin permiso de eliminar no puede borrar contactos', function () {
    $user = User::factory()->create();
    syncContactPermissions($user, [AppPermissions::CONTACTS_VIEW, AppPermissions::CONTACTS_EDIT]);

    $contact = Contact::query()->create([
        'name' => 'Contacto Protegido',
        'email' => 'protegido@example.com',
        'phone' => '5512345678',
    ]);

    actingAs($user)
        ->delete(route('contactos.destroy', $contact))
        ->assertForbidden();

    expect(Contact::query()->whereKey($contact->id)->exists())->toBeTrue();
});

test('usuario con permiso de eliminar puede borrar contactos', function () {
    $user = User::factory()->create();
    syncContactPermissions($user, [
        AppPermissions::CONTACTS_VIEW,
        AppPermissions::CONTACTS_EDIT,
        AppPermissions::CONTACTS_DELETE,
    ]);

    $contact = Contact::query()->create([
        'name' => 'Contacto Temporal',
        'email' => 'temporal@example.com',
        'phone' => '5577001122',
    ]);

    actingAs($user)
        ->delete(route('contactos.destroy', $contact))
        ->assertRedirect(route('contactos.index'));

    expect(Contact::query()->whereKey($contact->id)->exists())->toBeFalse();
});
