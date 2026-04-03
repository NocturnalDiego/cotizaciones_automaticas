<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <h2 class="font-semibold text-2xl text-slate-800 leading-tight tracking-tight">
                Cotización {{ $quote->folio }}
            </h2>
            <div class="flex items-center gap-2">
                <a href="{{ route('cotizaciones.edit', $quote) }}" class="inline-flex items-center rounded-lg px-4 py-2 text-sm font-semibold text-slate-700 border border-slate-300 hover:bg-slate-50 transition">
                    Editar
                </a>
                <a href="{{ route('cotizaciones.pdf', $quote) }}" class="inline-flex items-center rounded-lg px-4 py-2 text-sm font-semibold text-slate-700 border border-slate-300 hover:bg-slate-50 transition">
                    Descargar PDF
                </a>
                <a href="{{ route('cotizaciones.index') }}" class="inline-flex items-center rounded-lg px-4 py-2 text-sm font-semibold text-white bg-gradient-to-r from-sky-600 to-blue-700 shadow-md shadow-blue-900/20 hover:from-sky-500 hover:to-blue-600 transition">
                    Volver al listado
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-10">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            @if (session('status'))
                <x-auth-session-status :status="session('status')" />
            @endif

            <section class="quote-doc app-surface p-6 sm:p-10">
                <div class="grid gap-4 md:grid-cols-2">
                    <div>
                        <p class="quote-emitter">{{ $appBranding?->issuer_name ?? '' }}</p>
                        <p class="quote-emitter">R.F.C. {{ $appBranding?->issuer_rfc ?? '' }}</p>
                        <p class="quote-emitter">{{ $appBranding?->issuer_business_name ?? '' }}</p>
                    </div>
                    <div class="text-left md:text-right text-slate-700">
                        <p>{{ trim(($quote->location ?? '').' '.$quote->issued_at->translatedFormat('d \d\e F \d\e\l Y').'.') }}</p>
                        <p class="mt-1 font-semibold">{{ $quote->folio }}</p>
                    </div>
                </div>

                <h3 class="mt-8 text-xl font-bold tracking-wide text-slate-900">{{ $appBranding?->quote_brand_name ?? '' }}</h3>

                @php
                    $projectReference = trim((string) ($quote->reference_code ?? ''));
                    $clientName = trim((string) ($quote->client_name ?? ''));
                    $pedidoPresentacion = $projectReference;

                    if ($projectReference !== '' && $clientName !== '') {
                        $pedidoPresentacion .= ' - '.$clientName;
                    }
                @endphp

                <p class="mt-6 text-slate-800 leading-7">
                    En atención a su solicitud de cotización y de acuerdo a datos proporcionados por usted,
                    nos da mucho gusto presentarle nuestra cotización del pedido {{ $pedidoPresentacion }},
                    la cual consiste en:
                </p>

                <div class="mt-6 space-y-4">
                    @foreach ($quote->items as $item)
                        <div>
                            <p class="text-slate-900 font-medium leading-7">{{ $item->description }}</p>
                            <p class="quote-cost-line">
                                <span>Con un costo de</span>
                                <span>${{ number_format((float) $item->line_total, 2) }} + IVA</span>
                            </p>
                        </div>
                    @endforeach
                </div>

                <div class="mt-8 space-y-2 text-base">
                    <p class="quote-total-line">
                        <span>Valor del proyecto</span>
                        <span>${{ number_format((float) $quote->subtotal, 2) }} + IVA</span>
                    </p>
                    <p class="quote-total-line text-emerald-700">
                        <span>Total recibido</span>
                        <span>${{ number_format((float) $quote->paid_total, 2) }} + IVA</span>
                    </p>
                    @if ($quote->payments->isNotEmpty())
                        <p class="quote-total-line text-rose-700">
                            <span>Saldo pendiente</span>
                            <span>${{ number_format((float) $quote->balance_due, 2) }} + IVA</span>
                        </p>
                    @endif
                </div>

                @if ($quote->payments->isNotEmpty())
                    <div class="mt-8">
                        <h4 class="text-sm font-semibold uppercase tracking-wide text-slate-600">Anticipos registrados</h4>
                        <div class="mt-2 space-y-2">
                            @foreach ($quote->payments as $payment)
                                <div class="rounded-lg border border-slate-200 bg-slate-50/60 px-3 py-2">
                                    <p class="quote-total-line text-sm">
                                        <span>{{ $payment->label }} ({{ $payment->received_at->format('d/m/Y') }})</span>
                                        <span>${{ number_format((float) $payment->amount, 2) }} + IVA</span>
                                    </p>
                                    <div class="quote-payment-actions mt-2">
                                        <a href="{{ route('cotizaciones.anticipos.edit', [$quote, $payment]) }}" class="quote-payment-btn quote-payment-btn-edit">
                                            Editar
                                        </a>
                                        <form method="POST" action="{{ route('cotizaciones.anticipos.destroy', [$quote, $payment]) }}" class="quote-payment-actions-form" onsubmit="return confirm('¿Seguro que deseas eliminar este anticipo?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="quote-payment-btn quote-payment-btn-delete">
                                                Eliminar
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                <p class="mt-8 text-slate-700 leading-7">{{ $quote->terms ?? '' }}</p>

                <div class="mt-8 text-slate-800 space-y-1">
                    <p>{{ $quote->contact_phone ?? '' }}</p>
                    <p>{{ $quote->contact_email ?? '' }}</p>
                    <p class="font-semibold">{{ $quote->contact_name ?? '' }}</p>
                </div>
            </section>

            <section class="app-surface p-6 sm:p-8">
                <h3 class="text-lg font-semibold text-slate-900">Registrar anticipo</h3>
                <p class="text-sm text-slate-500 mt-1">Utiliza este bloque cuando la misma cotización reciba pagos en fechas posteriores.</p>

                <form method="POST" action="{{ route('cotizaciones.anticipos.store', $quote) }}" class="mt-5 grid gap-4 md:grid-cols-2">
                    @csrf

                    <div>
                        <x-input-label for="label" value="Concepto" />
                        <x-text-input id="label" name="label" type="text" class="mt-1 block w-full" :value="old('label', 'Anticipo')" required />
                        <x-input-error :messages="$errors->get('label')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="received_at" value="Fecha de recepción" />
                        <x-text-input id="received_at" name="received_at" type="date" class="mt-1 block w-full" :value="old('received_at', now()->format('Y-m-d'))" required />
                        <x-input-error :messages="$errors->get('received_at')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="amount" value="Monto recibido" />
                        <x-text-input id="amount" name="amount" type="number" min="0" step="0.01" class="mt-1 block w-full" :value="old('amount')" required />
                        <x-input-error :messages="$errors->get('amount')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="notes" value="Notas" />
                        <x-text-input id="notes" name="notes" type="text" class="mt-1 block w-full" :value="old('notes')" />
                        <x-input-error :messages="$errors->get('notes')" class="mt-2" />
                    </div>

                    <div class="md:col-span-2 flex justify-end">
                        <x-primary-button>
                            Guardar anticipo
                        </x-primary-button>
                    </div>
                </form>
            </section>
        </div>
    </div>
</x-app-layout>
