<?php

namespace Database\Seeders;

use App\Models\User;
use App\Support\AppPermissions;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permissions = collect(AppPermissions::all())
            ->map(fn (string $permissionName): Permission => Permission::findOrCreate($permissionName, 'web'));

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $adminRole = Role::findOrCreate('administrador', 'web');
        $adminRole->syncPermissions($permissions);

        $userRole = Role::findOrCreate('usuario', 'web');
        $userRole->syncPermissions(
            $permissions->where('name', AppPermissions::QUOTES_VIEW)->values()
        );

        $admin = User::updateOrCreate([
            'email' => 'user@test.com',
        ], [
            'name' => 'User Test',
            'password' => '12345678',
        ]);

        $admin->syncRoles([$adminRole->name]);
        $admin->syncPermissions($permissions);
    }
}
