<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <h2 class="font-semibold text-2xl text-slate-800 leading-tight tracking-tight">
                Cotizaciones
            </h2>
            <a href="{{ route('cotizaciones.create') }}" class="inline-flex items-center justify-center rounded-lg px-4 py-2 text-sm font-semibold text-white bg-gradient-to-r from-sky-600 to-blue-700 shadow-md shadow-blue-900/20 hover:from-sky-500 hover:to-blue-600 transition">
                Nueva cotización
            </a>
        </div>
    </x-slot>

    <div class="py-10">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <section class="app-surface overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200">
                        <thead class="bg-slate-50/80 text-slate-600 text-sm">
                            <tr>
                                <th class="px-4 py-3 text-left font-semibold">Folio</th>
                                <th class="px-4 py-3 text-left font-semibold">Pedido</th>
                                <th class="px-4 py-3 text-left font-semibold">Cliente</th>
                                <th class="px-4 py-3 text-left font-semibold">Fecha</th>
                                <th class="px-4 py-3 text-right font-semibold">Valor del proyecto</th>
                                <th class="px-4 py-3 text-right font-semibold">Pagado</th>
                                <th class="px-4 py-3 text-right font-semibold">Saldo</th>
                                <th class="px-4 py-3 text-left font-semibold">Estado</th>
                                <th class="px-4 py-3 text-right font-semibold">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 text-sm text-slate-700">
                            @forelse ($quotes as $quote)
                                <tr>
                                    <td class="px-4 py-3 font-semibold text-slate-900">{{ $quote->folio }}</td>
                                    <td class="px-4 py-3">{{ $quote->reference_code ?? '' }}</td>
                                    <td class="px-4 py-3">{{ $quote->client_name }}</td>
                                    <td class="px-4 py-3">{{ $quote->issued_at->format('d/m/Y') }}</td>
                                    <td class="px-4 py-3 text-right">${{ number_format((float) $quote->total, 2) }} + IVA</td>
                                    <td class="px-4 py-3 text-right text-emerald-700">${{ number_format((float) $quote->paid_total, 2) }} + IVA</td>
                                    <td class="px-4 py-3 text-right text-rose-700">${{ number_format((float) $quote->balance_due, 2) }} + IVA</td>
                                    <td class="px-4 py-3">
                                        <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold bg-sky-50 text-sky-700 border border-sky-200">
                                            {{ str_replace('_', ' ', ucfirst($quote->status)) }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        <div class="quote-table-actions">
                                            <a href="{{ route('cotizaciones.view', $quote) }}" class="quote-table-action-btn quote-table-action-btn-view">Ver</a>
                                            <a href="{{ route('cotizaciones.edit', $quote) }}" class="quote-table-action-btn quote-table-action-btn-edit">Editar</a>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="9" class="px-4 py-10 text-center text-slate-500">
                                        Aún no hay cotizaciones registradas.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="px-4 py-3 border-t border-slate-200">
                    {{ $quotes->links() }}
                </div>
            </section>
        </div>
    </div>
</x-app-layout>
