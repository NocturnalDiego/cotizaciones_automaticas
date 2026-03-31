<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <h2 class="font-semibold text-2xl text-slate-800 leading-tight tracking-tight">
                Gestión de usuarios
            </h2>
            <a href="{{ route('usuarios.create') }}" class="inline-flex items-center justify-center rounded-lg px-4 py-2 text-sm font-semibold text-white bg-gradient-to-r from-sky-600 to-blue-700 shadow-md shadow-blue-900/20 hover:from-sky-500 hover:to-blue-600 transition">
                Nuevo usuario
            </a>
        </div>
    </x-slot>

    <div class="py-10">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">
            @if (session('status'))
                <x-auth-session-status :status="session('status')" />
            @endif

            @if ($errors->has('users'))
                <div class="rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
                    {{ $errors->first('users') }}
                </div>
            @endif

            <section class="app-surface overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200">
                        <thead class="bg-slate-50/80 text-slate-600 text-sm">
                            <tr>
                                <th class="px-4 py-3 text-left font-semibold">Nombre</th>
                                <th class="px-4 py-3 text-left font-semibold">Correo</th>
                                <th class="px-4 py-3 text-left font-semibold">Permisos</th>
                                <th class="px-4 py-3 text-left font-semibold">Telegram</th>
                                <th class="px-4 py-3 text-right font-semibold">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 text-sm text-slate-700">
                            @forelse ($users as $managedUser)
                                <tr>
                                    <td class="px-4 py-3 font-semibold text-slate-900">{{ $managedUser->name }}</td>
                                    <td class="px-4 py-3">{{ $managedUser->email }}</td>
                                    <td class="px-4 py-3 w-64 align-top">
                                        @if ($managedUser->permissions->isEmpty())
                                            <span class="text-slate-400">Sin permisos asignados</span>
                                        @else
                                            <div class="flex max-w-[13rem] flex-wrap gap-2 sm:max-w-none">
                                                @foreach ($managedUser->permissions as $permission)
                                                    <span class="inline-flex items-center rounded-full border border-slate-200 bg-slate-50 px-2.5 py-1 text-center text-[11px] font-semibold leading-4 text-slate-600 sm:text-xs">
                                                        {{ $permissionLabels[$permission->name] ?? ucwords(str_replace(['.', '_'], ' ', $permission->name)) }}
                                                    </span>
                                                @endforeach
                                            </div>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 align-top">
                                        @if ($managedUser->telegram_chat_id)
                                            <div class="space-y-2">
                                                <div class="inline-flex items-center rounded-full border border-emerald-200 bg-emerald-50 px-2.5 py-1 text-[11px] font-semibold text-emerald-700 sm:text-xs">
                                                    Vinculado
                                                </div>
                                                <p class="text-xs text-slate-500">Chat: {{ $managedUser->telegram_chat_id }}</p>
                                                <form method="POST" action="{{ route('usuarios.telegram.revoke', $managedUser) }}" onsubmit="return confirm('¿Seguro que deseas revocar la autorización de Telegram para este usuario?');">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="inline-flex items-center rounded-md border border-amber-300 bg-amber-50 px-3 py-1.5 text-xs font-semibold text-amber-700 hover:bg-amber-100 transition">
                                                        Revocar autorización
                                                    </button>
                                                </form>
                                            </div>
                                        @else
                                            <span class="inline-flex items-center rounded-full border border-slate-200 bg-slate-50 px-2.5 py-1 text-[11px] font-semibold text-slate-500 sm:text-xs">
                                                Sin vincular
                                            </span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        <div class="inline-flex gap-2">
                                            <a href="{{ route('usuarios.edit', $managedUser) }}" class="inline-flex items-center rounded-md border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50 transition">
                                                Editar
                                            </a>

                                            @if (auth()->id() !== $managedUser->id)
                                                <form method="POST" action="{{ route('usuarios.destroy', $managedUser) }}" onsubmit="return confirm('¿Seguro que deseas eliminar este usuario?');">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="inline-flex items-center rounded-md border border-rose-300 bg-rose-50 px-3 py-1.5 text-xs font-semibold text-rose-700 hover:bg-rose-100 transition">
                                                        Eliminar
                                                    </button>
                                                </form>
                                            @else
                                                <span class="inline-flex items-center rounded-md border border-slate-200 bg-slate-50 px-3 py-1.5 text-xs font-semibold text-slate-400">
                                                    Sesión actual
                                                </span>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-4 py-10 text-center text-slate-500">No hay usuarios registrados.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="px-4 py-3 border-t border-slate-200">
                    {{ $users->links() }}
                </div>
            </section>
        </div>
    </div>
</x-app-layout>
