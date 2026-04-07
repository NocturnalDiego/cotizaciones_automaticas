<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreManagedUserRequest;
use App\Http\Requests\UpdateManagedUserRequest;
use App\Models\User;
use App\Support\AppPermissions;
use App\Services\Telegram\TelegramUserLinkService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Illuminate\View\View;
use Spatie\Permission\Models\Permission;

class UserManagementController extends Controller
{
    public function __construct(
        private readonly TelegramUserLinkService $telegramUserLinkService,
    ) {
    }

    public function index(): View
    {
        $users = User::query()
            ->with('permissions')
            ->orderBy('name')
            ->paginate(12);

        return view('usuarios.index', [
            'users' => $users,
            'permissionLabels' => $this->permissionBadgeOptions(),
        ]);
    }

    public function create(): View
    {
        return view('usuarios.create', [
            'user' => new User(),
            'availablePermissions' => $this->permissionOptions(),
            'selectedPermissions' => [],
        ]);
    }

    public function store(StoreManagedUserRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $managedUser = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => $validated['password'],
        ]);

        $this->ensurePermissionsExist();
        $managedUser->syncPermissions(
            $this->sanitizeAssignablePermissions($validated['permissions'] ?? [])
        );

        return Redirect::route('usuarios.index')->with('status', 'Usuario creado correctamente.');
    }

    public function edit(User $user): View
    {
        return view('usuarios.edit', [
            'user' => $user,
            'availablePermissions' => $this->permissionOptions(),
            'selectedPermissions' => $user->permissions->pluck('name')->all(),
        ]);
    }

    public function update(UpdateManagedUserRequest $request, User $user): RedirectResponse
    {
        $validated = $request->validated();

        $payload = [
            'name' => $validated['name'],
            'email' => $validated['email'],
        ];

        if (($validated['password'] ?? '') !== '') {
            $payload['password'] = $validated['password'];
        }

        $user->update($payload);
        $this->ensurePermissionsExist();
        $user->syncPermissions(
            $this->sanitizeAssignablePermissions($validated['permissions'] ?? [])
        );

        return Redirect::route('usuarios.index')->with('status', 'Usuario actualizado correctamente.');
    }

    public function destroy(User $user): RedirectResponse
    {
        if (Auth::id() === $user->id) {
            return Redirect::route('usuarios.index')
                ->withErrors(['users' => 'No puedes eliminar tu propia cuenta mientras tienes la sesión iniciada.']);
        }

        $user->delete();

        return Redirect::route('usuarios.index')->with('status', 'Usuario eliminado correctamente.');
    }

    public function revokeTelegram(User $user): RedirectResponse
    {
        $this->telegramUserLinkService->revokeAuthorization($user);

        return Redirect::route('usuarios.index')->with('status', 'Autorización de Telegram revocada correctamente.');
    }

    /**
     * @return array<string, string>
     */
    private function permissionOptions(): array
    {
        $options = [
            AppPermissions::QUOTES_VIEW => 'Puede ver cotizaciones',
            AppPermissions::QUOTES_CREATE => 'Puede crear cotizaciones',
            AppPermissions::QUOTES_EDIT => 'Puede editar cotizaciones y anticipos',
            AppPermissions::QUOTES_DELETE => 'Puede eliminar cotizaciones',
            AppPermissions::CONTACTS_VIEW => 'Puede ver contactos',
            AppPermissions::CONTACTS_EDIT => 'Puede crear y editar contactos',
            AppPermissions::CONTACTS_DELETE => 'Puede eliminar contactos',
            AppPermissions::BRANDING_MANAGE => 'Puede gestionar identidad de marca',
            AppPermissions::USERS_MANAGE => 'Puede gestionar usuarios',
        ];

        if (!$this->currentUserIsAdministrator()) {
            unset($options[AppPermissions::QUOTES_DELETE]);
            unset($options[AppPermissions::CONTACTS_DELETE]);
        }

        return $options;
    }

    /**
     * @return array<string, string>
     */
    private function permissionBadgeOptions(): array
    {
        return [
            AppPermissions::QUOTES_VIEW => 'Ver cotizaciones',
            AppPermissions::QUOTES_CREATE => 'Crear cotizaciones',
            AppPermissions::QUOTES_EDIT => 'Editar cotizaciones',
            AppPermissions::QUOTES_DELETE => 'Eliminar cotizaciones',
            AppPermissions::CONTACTS_VIEW => 'Ver contactos',
            AppPermissions::CONTACTS_EDIT => 'Editar contactos',
            AppPermissions::CONTACTS_DELETE => 'Eliminar contactos',
            AppPermissions::BRANDING_MANAGE => 'Gestionar marca',
            AppPermissions::USERS_MANAGE => 'Gestionar usuarios',
        ];
    }

    /**
     * @param  array<int, mixed>  $permissions
     * @return array<int, string>
     */
    private function sanitizeAssignablePermissions(array $permissions): array
    {
        $normalizedPermissions = array_values(array_filter(
            array_map(static fn (mixed $permission): string => (string) $permission, $permissions),
            static fn (string $permission): bool => $permission !== ''
        ));

        if ($this->currentUserIsAdministrator()) {
            return $normalizedPermissions;
        }

        return array_values(array_filter(
            $normalizedPermissions,
            static fn (string $permission): bool => !in_array($permission, [
                AppPermissions::QUOTES_DELETE,
                AppPermissions::CONTACTS_DELETE,
            ], true)
        ));
    }

    private function ensurePermissionsExist(): void
    {
        foreach (AppPermissions::all() as $permissionName) {
            Permission::findOrCreate($permissionName, 'web');
        }
    }

    private function currentUserIsAdministrator(): bool
    {
        $authenticatedUser = Auth::user();

        return $authenticatedUser instanceof User
            && $authenticatedUser->hasRole('administrador');
    }
}
