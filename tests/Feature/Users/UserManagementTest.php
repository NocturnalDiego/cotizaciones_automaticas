<?php

use App\Models\User;
use App\Support\AppPermissions;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use function Pest\Laravel\actingAs;

function grantUsersManagePermission(User $user): void
{
    foreach (AppPermissions::all() as $permissionName) {
        Permission::findOrCreate($permissionName, 'web');
    }

    $user->syncPermissions([AppPermissions::USERS_MANAGE]);
}

test('usuario sin permiso no puede acceder a gestion de usuarios', function () {
    $user = User::factory()->create();

    actingAs($user)
        ->get(route('usuarios.index'))
        ->assertForbidden();
});

test('administrador puede crear, editar y eliminar usuarios', function () {
    $admin = User::factory()->create();
    grantUsersManagePermission($admin);

    $createResponse = actingAs($admin)
        ->post(route('usuarios.store'), [
            'name' => 'Usuario Operativo',
            'email' => 'usuario.operativo@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'permissions' => [
                AppPermissions::QUOTES_VIEW,
                AppPermissions::QUOTES_CREATE,
            ],
        ]);

    $createResponse->assertRedirect(route('usuarios.index'));

    $managedUser = User::query()->where('email', 'usuario.operativo@example.com')->first();

    expect($managedUser)->not->toBeNull();
    expect($managedUser?->can(AppPermissions::QUOTES_VIEW))->toBeTrue();
    expect($managedUser?->can(AppPermissions::QUOTES_CREATE))->toBeTrue();

    $updateResponse = actingAs($admin)
        ->put(route('usuarios.update', $managedUser), [
            'name' => 'Usuario Operativo Editado',
            'email' => 'usuario.operativo@example.com',
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
            'permissions' => [
                AppPermissions::QUOTES_VIEW,
            ],
        ]);

    $updateResponse->assertRedirect(route('usuarios.index'));

    $managedUser->refresh();

    expect($managedUser->name)->toBe('Usuario Operativo Editado');
    expect(Hash::check('newpassword123', (string) $managedUser->password))->toBeTrue();
    expect($managedUser->can(AppPermissions::QUOTES_VIEW))->toBeTrue();
    expect($managedUser->can(AppPermissions::QUOTES_CREATE))->toBeFalse();

    actingAs($admin)
        ->delete(route('usuarios.destroy', $managedUser))
        ->assertRedirect(route('usuarios.index'));

    expect(User::query()->where('email', 'usuario.operativo@example.com')->exists())->toBeFalse();
});

test('administrador no puede eliminar su propia cuenta desde gestion de usuarios', function () {
    $admin = User::factory()->create();
    grantUsersManagePermission($admin);

    $response = actingAs($admin)
        ->delete(route('usuarios.destroy', $admin));

    $response
        ->assertRedirect(route('usuarios.index'))
        ->assertSessionHasErrors('users');

    expect($admin->fresh())->not->toBeNull();
});

test('administrador puede revocar autorizacion de telegram de un usuario', function () {
    $admin = User::factory()->create();
    grantUsersManagePermission($admin);

    $managedUser = User::factory()->create([
        'telegram_chat_id' => '123456789',
        'telegram_linked_at' => now(),
    ]);

    actingAs($admin)
        ->delete(route('usuarios.telegram.revoke', $managedUser))
        ->assertRedirect(route('usuarios.index'));

    $managedUser->refresh();

    expect($managedUser->telegram_chat_id)->toBeNull();
    expect($managedUser->telegram_linked_at)->toBeNull();
});
