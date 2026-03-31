<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-2xl text-slate-800 leading-tight tracking-tight">
            Editar anticipo de {{ $quote->folio }}
        </h2>
    </x-slot>

    <div class="py-10">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <section class="app-surface p-6 sm:p-8">
                <form method="POST" action="{{ route('cotizaciones.anticipos.update', [$quote, $payment]) }}" class="space-y-6">
                    @csrf
                    @method('PUT')

                    <div class="grid gap-4 md:grid-cols-2">
                        <div>
                            <x-input-label for="label" value="Concepto" />
                            <x-text-input id="label" name="label" type="text" class="mt-1 block w-full" :value="old('label', $payment->label)" required />
                            <x-input-error :messages="$errors->get('label')" class="mt-2" />
                        </div>

                        <div>
                            <x-input-label for="received_at" value="Fecha de recepción" />
                            <x-text-input id="received_at" name="received_at" type="date" class="mt-1 block w-full" :value="old('received_at', $payment->received_at->format('Y-m-d'))" required />
                            <x-input-error :messages="$errors->get('received_at')" class="mt-2" />
                        </div>

                        <div>
                            <x-input-label for="amount" value="Monto recibido" />
                            <x-text-input id="amount" name="amount" type="number" min="0" step="0.01" class="mt-1 block w-full" :value="old('amount', (float) $payment->amount)" required />
                            <x-input-error :messages="$errors->get('amount')" class="mt-2" />
                        </div>

                        <div>
                            <x-input-label for="notes" value="Notas" />
                            <x-text-input id="notes" name="notes" type="text" class="mt-1 block w-full" :value="old('notes', $payment->notes)" />
                            <x-input-error :messages="$errors->get('notes')" class="mt-2" />
                        </div>
                    </div>

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
