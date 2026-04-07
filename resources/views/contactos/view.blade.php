<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <h2 class="font-semibold text-2xl text-slate-800 leading-tight tracking-tight">
                Contacto
            </h2>
            <div class="flex items-center gap-2">
                @can(App\Support\AppPermissions::CONTACTS_EDIT)
                    <a href="{{ route('contactos.edit', $contact) }}" class="inline-flex items-center rounded-lg px-4 py-2 text-sm font-semibold text-slate-700 border border-slate-300 hover:bg-slate-50 transition">
                        Editar
                    </a>
                @endcan
                <a href="{{ route('contactos.index') }}" class="inline-flex items-center rounded-lg px-4 py-2 text-sm font-semibold text-white bg-gradient-to-r from-sky-600 to-blue-700 shadow-md shadow-blue-900/20 hover:from-sky-500 hover:to-blue-600 transition">
                    Volver al listado
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-10">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-6">
            @if (session('status'))
                <x-auth-session-status :status="session('status')" />
            @endif

            <section class="app-surface p-6 sm:p-8">
                <dl class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <dt class="text-sm font-semibold uppercase tracking-wide text-slate-500">Nombre</dt>
                        <dd class="mt-1 text-slate-900">{{ $contact->name }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-semibold uppercase tracking-wide text-slate-500">Correo</dt>
                        <dd class="mt-1 text-slate-900">{{ $contact->email ?? 'Sin dato' }}</dd>
                    </div>
                    <div class="sm:col-span-2">
                        <dt class="text-sm font-semibold uppercase tracking-wide text-slate-500">Teléfono</dt>
                        <dd class="mt-1 text-slate-900">{{ $contact->phone ?? 'Sin dato' }}</dd>
                    </div>
                </dl>
            </section>
        </div>
    </div>
</x-app-layout>
