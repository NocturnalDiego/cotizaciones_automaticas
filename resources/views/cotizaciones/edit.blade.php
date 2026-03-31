<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-2xl text-slate-800 leading-tight tracking-tight">
            Editar cotización {{ $quote->folio }}
        </h2>
    </x-slot>

    <div class="py-10">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <section class="app-surface p-6 sm:p-8">
                <form method="POST" action="{{ route('cotizaciones.update', $quote) }}" class="space-y-6">
                    @csrf
                    @method('PUT')

                    @include('cotizaciones.partials.form', ['quote' => $quote])

                    <div class="flex items-center justify-end gap-3">
                        <a href="{{ route('cotizaciones.view', $quote) }}" class="inline-flex items-center rounded-lg px-4 py-2 text-sm font-semibold text-slate-700 border border-slate-300 hover:bg-slate-50 transition">
                            Volver
                        </a>
                        <x-primary-button>
                            Guardar cambios
                        </x-primary-button>
                    </div>
                </form>
            </section>
        </div>
    </div>
</x-app-layout>
