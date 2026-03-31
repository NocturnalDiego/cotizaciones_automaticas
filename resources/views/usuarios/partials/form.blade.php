@php
    $isEdit = $user->exists;
@endphp

<div class="grid gap-4 md:grid-cols-2">
    <div>
        <x-input-label for="name" value="Nombre" />
        <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" :value="old('name', $user->name)" required />
        <x-input-error :messages="$errors->get('name')" class="mt-2" />
    </div>

    <div>
        <x-input-label for="email" value="Correo" />
        <x-text-input id="email" name="email" type="email" class="mt-1 block w-full" :value="old('email', $user->email)" required />
        <x-input-error :messages="$errors->get('email')" class="mt-2" />
    </div>

    <div>
        <x-input-label for="password" :value="$isEdit ? 'Nueva contraseña (opcional)' : 'Contraseña'" />
        <x-text-input id="password" name="password" type="password" class="mt-1 block w-full" :required="!$isEdit" />
        @if ($isEdit)
            <p class="mt-1 text-xs text-slate-500">Si quieres mantener la contraseña actual de este usuario, deja este campo en blanco.</p>
        @endif
        <x-input-error :messages="$errors->get('password')" class="mt-2" />
    </div>

    <div>
        <x-input-label for="password_confirmation" :value="$isEdit ? 'Confirmar nueva contraseña' : 'Confirmar contraseña'" />
        <x-text-input id="password_confirmation" name="password_confirmation" type="password" class="mt-1 block w-full" :required="!$isEdit" />
    </div>
</div>

<div class="mt-6">
    <h3 class="text-sm font-semibold uppercase tracking-wide text-slate-600">Permisos</h3>
    <p class="mt-1 text-sm text-slate-500">Selecciona exactamente lo que este usuario podrá hacer en el sistema.</p>

    <div class="mt-3 grid gap-3 sm:grid-cols-2">
        @foreach ($availablePermissions as $permissionName => $permissionLabel)
            <label class="flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700">
                <input
                    type="checkbox"
                    name="permissions[]"
                    value="{{ $permissionName }}"
                    class="rounded border-gray-300 text-sky-600 shadow-sm focus:ring-sky-500"
                    @checked(in_array($permissionName, old('permissions', $selectedPermissions), true))
                >
                <span>{{ $permissionLabel }}</span>
            </label>
        @endforeach
    </div>
    <x-input-error :messages="$errors->get('permissions')" class="mt-2" />
    <x-input-error :messages="$errors->get('permissions.*')" class="mt-2" />
</div>
