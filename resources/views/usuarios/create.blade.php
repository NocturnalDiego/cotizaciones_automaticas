<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-3">
            <h2 class="font-semibold text-2xl text-slate-800 leading-tight tracking-tight">
                Nuevo usuario
            </h2>
            <a href="{{ route('usuarios.index') }}" class="inline-flex items-center rounded-lg px-4 py-2 text-sm font-semibold text-slate-700 border border-slate-300 hover:bg-slate-50 transition">
                Volver al listado
            </a>
        </div>
    </x-slot>

    <div class="py-10">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            <section class="app-surface p-6 sm:p-8">
                <form method="POST" action="{{ route('usuarios.store') }}">
                    @csrf

                    @include('usuarios.partials.form')

                    <div class="mt-6 flex justify-end">
                        <x-primary-button>
                            Guardar usuario
                        </x-primary-button>
                    </div>
                </form>
            </section>
        </div>
    </div>
</x-app-layout>
