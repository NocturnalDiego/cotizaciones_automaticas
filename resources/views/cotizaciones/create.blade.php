<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-2xl text-slate-800 leading-tight tracking-tight">
            Nueva cotización
        </h2>
    </x-slot>

    <div class="py-10">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <section class="app-surface p-6 sm:p-8">
                <form method="POST" action="{{ route('cotizaciones.store') }}" class="space-y-6">
                    @csrf

                    @include('cotizaciones.partials.form', ['quote' => $quote])

                    <div class="flex items-center justify-end gap-3">
                        <a href="{{ route('cotizaciones.index') }}" class="inline-flex items-center rounded-lg px-4 py-2 text-sm font-semibold text-slate-700 border border-slate-300 hover:bg-slate-50 transition">
                            Cancelar
                        </a>
                        <x-primary-button>
                            Guardar cotización
                        </x-primary-button>
                    </div>
                </form>
            </section>
        </div>
    </div>
</x-app-layout>
