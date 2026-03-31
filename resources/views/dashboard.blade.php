<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <p class="text-sm font-semibold uppercase tracking-wide text-sky-700">Panel operativo</p>
                <h2 class="mt-1 font-semibold text-2xl text-slate-800 leading-tight tracking-tight">
                    Seguimiento de cotizaciones
                </h2>
                <p class="mt-1 text-sm text-slate-600">
                    Bienvenido, {{ Auth::user()->name }}. Aquí tienes visibilidad de cobranza, avance y actividad reciente.
                </p>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <a href="{{ route('cotizaciones.create') }}" class="inline-flex items-center gap-2 rounded-lg px-4 py-2 text-sm font-semibold text-white bg-gradient-to-r from-sky-600 to-blue-700 shadow-md shadow-blue-900/20 hover:from-sky-500 hover:to-blue-600 transition">
                    <x-tabler-icon name="plus" class="h-4 w-4" />
                    Nueva cotización
                </a>
                <a href="{{ route('cotizaciones.index') }}" class="inline-flex items-center gap-2 rounded-lg px-4 py-2 text-sm font-semibold text-slate-700 border border-slate-300 hover:bg-slate-50 transition">
                    <x-tabler-icon name="list-details" class="h-4 w-4" />
                    Ver listado
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-10">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <article class="app-surface p-5">
                    <div class="flex items-center justify-between">
                        <p class="text-sm font-medium text-slate-600">Cotizaciones del mes</p>
                        <x-tabler-icon name="file-invoice" class="h-5 w-5 text-sky-700" />
                    </div>
                    <p class="mt-2 text-3xl font-bold text-slate-900">{{ number_format((int) $kpis['quotes_this_month']) }}</p>
                    <p class="mt-1 text-xs text-slate-500">Total histórico: {{ number_format((int) $kpis['total_quotes']) }}</p>
                </article>

                <article class="app-surface p-5">
                    <div class="flex items-center justify-between">
                        <p class="text-sm font-medium text-slate-600">Cobranza pendiente</p>
                        <x-tabler-icon name="alert-circle" class="h-5 w-5 text-rose-700" />
                    </div>
                    <p class="mt-2 text-3xl font-bold text-slate-900">${{ number_format((float) $kpis['pending_amount'], 2) }}</p>
                    <p class="mt-1 text-xs text-slate-500">{{ number_format((int) $kpis['open_quotes']) }} cotizaciones con saldo por cobrar</p>
                </article>

                <article class="app-surface p-5">
                    <div class="flex items-center justify-between">
                        <p class="text-sm font-medium text-slate-600">Cobrado este mes</p>
                        <x-tabler-icon name="cash" class="h-5 w-5 text-emerald-700" />
                    </div>
                    <p class="mt-2 text-3xl font-bold text-slate-900">${{ number_format((float) $kpis['collected_this_month'], 2) }}</p>
                    <p class="mt-1 text-xs text-slate-500">Ingresos recibidos en el periodo actual</p>
                </article>

                <article class="app-surface p-5">
                    <div class="flex items-center justify-between">
                        <p class="text-sm font-medium text-slate-600">Ticket promedio</p>
                        <x-tabler-icon name="chart-donut-3" class="h-5 w-5 text-indigo-700" />
                    </div>
                    <p class="mt-2 text-3xl font-bold text-slate-900">${{ number_format((float) $kpis['average_ticket'], 2) }}</p>
                    <p class="mt-1 text-xs text-slate-500">Cotizaciones pagadas: {{ number_format((float) $kpis['paid_rate'], 1) }}%</p>
                </article>
            </section>

            <section class="app-surface p-6 sm:p-8">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h3 class="text-lg font-semibold text-slate-900">Distribución por estado</h3>
                        <p class="text-sm text-slate-500">Vista rápida para identificar dónde concentrar seguimiento comercial y de cobranza.</p>
                    </div>
                </div>

                <div class="mt-4 grid gap-3 sm:grid-cols-3">
                    @foreach ($statusSummary as $status)
                        <article class="rounded-xl border px-4 py-3 {{ $status['badge'] }}">
                            <p class="text-sm font-medium">{{ $status['label'] }}</p>
                            <p class="mt-1 text-2xl font-bold">{{ number_format((int) $status['total']) }}</p>
                        </article>
                    @endforeach
                </div>
            </section>

            @if ((int) $kpis['total_quotes'] === 0)
                <section class="app-surface p-8 text-center">
                    <h3 class="text-xl font-bold text-slate-900">Todavía no hay cotizaciones registradas.</h3>
                    <p class="mt-2 text-slate-600">Comienza creando la primera cotización para activar el seguimiento operativo desde este panel.</p>
                    <a href="{{ route('cotizaciones.create') }}" class="mt-5 inline-flex items-center gap-2 rounded-lg px-4 py-2 text-sm font-semibold text-white bg-gradient-to-r from-sky-600 to-blue-700 shadow-md shadow-blue-900/20 hover:from-sky-500 hover:to-blue-600 transition">
                        <x-tabler-icon name="plus" class="h-4 w-4" />
                        Crear cotización
                    </a>
                </section>
            @else
                <div class="grid gap-6 lg:grid-cols-3">
                    <section class="app-surface p-6 sm:p-8 lg:col-span-2">
                        <div class="flex items-center justify-between gap-3">
                            <div>
                                <h3 class="text-lg font-semibold text-slate-900">Prioridades de cobranza</h3>
                                <p class="text-sm text-slate-500">Cotizaciones con mayor saldo pendiente para seguimiento inmediato.</p>
                            </div>
                        </div>

                        <div class="mt-4 overflow-x-auto">
                            <table class="min-w-full divide-y divide-slate-200 text-sm">
                                <thead class="text-slate-600">
                                    <tr>
                                        <th class="py-2 text-left font-semibold">Folio</th>
                                        <th class="py-2 text-left font-semibold">Cliente</th>
                                        <th class="py-2 text-right font-semibold">Pagado</th>
                                        <th class="py-2 text-right font-semibold">Saldo</th>
                                        <th class="py-2 text-right font-semibold">Días abiertos</th>
                                        <th class="py-2 text-right font-semibold">Acción</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100 text-slate-700">
                                    @forelse ($priorityCollectionQuotes as $quote)
                                        <tr>
                                            <td class="py-2 font-semibold text-slate-900">{{ $quote->folio }}</td>
                                            <td class="py-2">{{ $quote->client_name }}</td>
                                            <td class="py-2 text-right text-emerald-700">${{ number_format((float) $quote->paid_total, 2) }}</td>
                                            <td class="py-2 text-right text-rose-700">${{ number_format((float) $quote->balance_due, 2) }}</td>
                                            <td class="py-2 text-right">{{ number_format((int) $quote->open_days) }}</td>
                                            <td class="py-2 text-right">
                                                <a href="{{ route('cotizaciones.view', $quote) }}" class="inline-flex items-center gap-1 rounded-md border border-slate-300 px-2.5 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50">
                                                    Ver
                                                    <x-tabler-icon name="arrow-right" class="h-3.5 w-3.5" />
                                                </a>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="6" class="py-6 text-center text-slate-500">No hay saldos pendientes en este momento.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </section>

                    <section class="app-surface p-6 sm:p-8">
                        <h3 class="text-lg font-semibold text-slate-900">Seguimiento recomendado</h3>
                        <p class="text-sm text-slate-500">Lista corta para priorizar la gestión del día.</p>

                        <div class="mt-4 space-y-3">
                            <article class="rounded-xl border border-sky-200 bg-sky-50 px-4 py-3">
                                <div class="flex items-center justify-between gap-2">
                                    <p class="text-sm font-semibold text-sky-900">Sin anticipo registrado</p>
                                    <x-tabler-icon name="clock-hour-4" class="h-4 w-4 text-sky-700" />
                                </div>
                                <p class="mt-1 text-2xl font-bold text-sky-900">{{ number_format($quotesNeedingAdvance->count()) }}</p>
                            </article>

                            <article class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3">
                                <div class="flex items-center justify-between gap-2">
                                    <p class="text-sm font-semibold text-amber-900">Con saldo por cobrar</p>
                                    <x-tabler-icon name="receipt-2" class="h-4 w-4 text-amber-700" />
                                </div>
                                <p class="mt-1 text-2xl font-bold text-amber-900">{{ number_format($quotesWithPartialPayment->count()) }}</p>
                            </article>
                        </div>

                        <div class="mt-4 space-y-2">
                            @forelse ($quotesNeedingAdvance->take(3) as $quote)
                                <a href="{{ route('cotizaciones.view', $quote) }}" class="flex items-center justify-between rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 hover:border-slate-300 hover:bg-slate-50 transition">
                                    <span class="font-semibold">{{ $quote->folio }}</span>
                                    <span class="text-slate-500">{{ $quote->issued_at->format('d/m/Y') }}</span>
                                </a>
                            @empty
                                <p class="text-sm text-slate-500">No hay cotizaciones sin anticipo pendientes de seguimiento.</p>
                            @endforelse
                        </div>
                    </section>
                </div>

                <section class="app-surface p-6 sm:p-8">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <h3 class="text-lg font-semibold text-slate-900">Actividad reciente</h3>
                            <p class="text-sm text-slate-500">Últimas cotizaciones generadas en el sistema.</p>
                        </div>
                        <a href="{{ route('cotizaciones.index') }}" class="inline-flex items-center gap-1 rounded-lg border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50">
                            Ver todo
                            <x-tabler-icon name="arrow-right" class="h-3.5 w-3.5" />
                        </a>
                    </div>

                    <div class="mt-4 grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                        @foreach ($recentQuotes as $quote)
                            @php
                                $statusBadgeClasses = match ($quote->status) {
                                    \App\Models\Quote::STATUS_PAGADA => 'bg-emerald-50 text-emerald-700 border-emerald-200',
                                    \App\Models\Quote::STATUS_CON_ANTICIPO => 'bg-amber-50 text-amber-700 border-amber-200',
                                    default => 'bg-sky-50 text-sky-700 border-sky-200',
                                };
                            @endphp
                            <a href="{{ route('cotizaciones.view', $quote) }}" class="rounded-xl border border-slate-200 bg-white p-4 hover:border-slate-300 hover:shadow-sm transition">
                                <div class="flex items-start justify-between gap-3">
                                    <div>
                                        <p class="text-sm text-slate-500">{{ $quote->issued_at->format('d/m/Y') }}</p>
                                        <p class="mt-1 font-semibold text-slate-900">{{ $quote->folio }}</p>
                                    </div>
                                    <span class="inline-flex rounded-full border px-2 py-0.5 text-xs font-semibold {{ $statusBadgeClasses }}">
                                        {{ str_replace('_', ' ', ucfirst($quote->status)) }}
                                    </span>
                                </div>

                                <p class="mt-2 text-sm text-slate-600 line-clamp-1">{{ $quote->client_name }}</p>

                                <div class="mt-3 flex items-center justify-between text-sm">
                                    <span class="text-slate-500">Valor</span>
                                    <span class="font-semibold text-slate-900">${{ number_format((float) $quote->total, 2) }}</span>
                                </div>
                                <div class="mt-1 flex items-center justify-between text-sm">
                                    <span class="text-slate-500">Saldo</span>
                                    <span class="font-semibold text-rose-700">${{ number_format((float) $quote->balance_due, 2) }}</span>
                                </div>
                            </a>
                        @endforeach
                    </div>
                </section>
            @endif
        </div>
    </div>
</x-app-layout>
