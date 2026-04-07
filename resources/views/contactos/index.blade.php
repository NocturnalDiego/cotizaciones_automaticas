<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <h2 class="font-semibold text-2xl text-slate-800 leading-tight tracking-tight">
                Contactos
            </h2>

            @can(App\Support\AppPermissions::CONTACTS_EDIT)
                <a href="{{ route('contactos.create') }}" class="inline-flex items-center justify-center gap-2 rounded-lg px-4 py-2 text-sm font-semibold text-white bg-gradient-to-r from-sky-600 to-blue-700 shadow-md shadow-blue-900/20 hover:from-sky-500 hover:to-blue-600 transition">
                    <x-tabler-icon name="plus" size="18" />
                    <span>Nuevo contacto</span>
                </a>
            @endcan
        </div>
    </x-slot>

    <div class="py-10">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            @if (session('status'))
                <x-auth-session-status :status="session('status')" class="mb-4" />
            @endif

            <section class="app-surface overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200">
                        <thead class="bg-slate-50/80 text-slate-600 text-sm">
                            <tr>
                                <th class="px-4 py-3 text-left font-semibold">Nombre</th>
                                <th class="px-4 py-3 text-left font-semibold">Correo</th>
                                <th class="px-4 py-3 text-left font-semibold">Teléfono</th>
                                <th class="px-4 py-3 text-right font-semibold">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 text-sm text-slate-700">
                            @forelse ($contacts as $contact)
                                <tr>
                                    <td class="px-4 py-3 font-semibold text-slate-900">{{ $contact->name }}</td>
                                    <td class="px-4 py-3">{{ $contact->email ?? 'Sin dato' }}</td>
                                    <td class="px-4 py-3">{{ $contact->phone ?? 'Sin dato' }}</td>
                                    <td class="px-4 py-3 text-right">
                                        <div class="inline-flex items-center gap-2">
                                            <a href="{{ route('contactos.view', $contact) }}" class="inline-flex items-center rounded-lg px-3 py-2 text-sm font-semibold text-slate-700 border border-slate-300 hover:bg-slate-50 transition">
                                                Ver
                                            </a>

                                            @can(App\Support\AppPermissions::CONTACTS_EDIT)
                                                <a href="{{ route('contactos.edit', $contact) }}" class="inline-flex items-center rounded-lg px-3 py-2 text-sm font-semibold text-sky-700 border border-sky-300 hover:bg-sky-50 transition">
                                                    Editar
                                                </a>
                                            @endcan

                                            @can(App\Support\AppPermissions::CONTACTS_DELETE)
                                                <form method="POST" action="{{ route('contactos.destroy', $contact) }}" onsubmit="return confirm('¿Seguro que deseas eliminar este contacto?');">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="inline-flex items-center rounded-lg px-3 py-2 text-sm font-semibold text-rose-700 border border-rose-300 hover:bg-rose-50 transition">
                                                        Eliminar
                                                    </button>
                                                </form>
                                            @endcan
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="px-4 py-10 text-center text-slate-500">
                                        Aún no hay contactos registrados.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="px-4 py-3 border-t border-slate-200">
                    {{ $contacts->links() }}
                </div>
            </section>
        </div>
    </div>
</x-app-layout>
