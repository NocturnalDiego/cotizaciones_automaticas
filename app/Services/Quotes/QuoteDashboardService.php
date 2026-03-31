<?php

namespace App\Services\Quotes;

use App\Models\Quote;
use App\Models\QuotePayment;
use Illuminate\Support\Facades\DB;

class QuoteDashboardService
{
    /**
     * @return array<string, mixed>
     */
    public function getData(): array
    {
        $today = now()->startOfDay();
        $monthStart = $today->copy()->startOfMonth()->toDateString();
        $monthEnd = $today->copy()->endOfMonth()->toDateString();

        $totalQuotes = Quote::query()->count();
        $openQuotes = Quote::query()->where('balance_due', '>', 0)->count();
        $paidQuotes = Quote::query()->where('status', Quote::STATUS_PAGADA)->count();

        $kpis = [
            'total_quotes' => $totalQuotes,
            'quotes_this_month' => Quote::query()
                ->whereBetween('issued_at', [$monthStart, $monthEnd])
                ->count(),
            'open_quotes' => $openQuotes,
            'pending_amount' => (float) Quote::query()
                ->where('balance_due', '>', 0)
                ->sum('balance_due'),
            'collected_this_month' => (float) QuotePayment::query()
                ->whereBetween('received_at', [$monthStart, $monthEnd])
                ->sum('amount'),
            'average_ticket' => (float) Quote::query()->avg('total'),
            'paid_rate' => $totalQuotes > 0
                ? round(($paidQuotes / $totalQuotes) * 100, 1)
                : 0.0,
        ];

        $priorityCollectionQuotes = Quote::query()
            ->select([
                'id',
                'folio',
                'client_name',
                'issued_at',
                'status',
                'total',
                'paid_total',
                'balance_due',
            ])
            ->where('balance_due', '>', 0)
            ->orderByDesc('balance_due')
            ->orderBy('issued_at')
            ->limit(5)
            ->get();

        $priorityCollectionQuotes->each(function (Quote $quote) use ($today): void {
            $quote->setAttribute('open_days', (int) $quote->issued_at->diffInDays($today));
        });

        return [
            'kpis' => $kpis,
            'statusSummary' => $this->buildStatusSummary(),
            'recentQuotes' => Quote::query()
                ->select(['id', 'folio', 'client_name', 'issued_at', 'status', 'total', 'balance_due'])
                ->latest('issued_at')
                ->latest('id')
                ->limit(6)
                ->get(),
            'priorityCollectionQuotes' => $priorityCollectionQuotes,
            'quotesNeedingAdvance' => Quote::query()
                ->select(['id', 'folio', 'client_name', 'issued_at', 'balance_due'])
                ->where('status', Quote::STATUS_EMITIDA)
                ->orderBy('issued_at')
                ->limit(5)
                ->get(),
            'quotesWithPartialPayment' => Quote::query()
                ->select(['id', 'folio', 'client_name', 'issued_at', 'paid_total', 'balance_due'])
                ->where('status', Quote::STATUS_CON_ANTICIPO)
                ->where('balance_due', '>', 0)
                ->orderByDesc('balance_due')
                ->limit(5)
                ->get(),
        ];
    }

    /**
     * @return array<int, array<string, string|int>>
     */
    private function buildStatusSummary(): array
    {
        $statusTotals = Quote::query()
            ->select('status', DB::raw('count(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status');

        return [
            [
                'key' => Quote::STATUS_EMITIDA,
                'label' => 'Emitidas',
                'total' => (int) ($statusTotals[Quote::STATUS_EMITIDA] ?? 0),
                'badge' => 'bg-sky-50 text-sky-700 border-sky-200',
            ],
            [
                'key' => Quote::STATUS_CON_ANTICIPO,
                'label' => 'Con anticipo',
                'total' => (int) ($statusTotals[Quote::STATUS_CON_ANTICIPO] ?? 0),
                'badge' => 'bg-amber-50 text-amber-700 border-amber-200',
            ],
            [
                'key' => Quote::STATUS_PAGADA,
                'label' => 'Pagadas',
                'total' => (int) ($statusTotals[Quote::STATUS_PAGADA] ?? 0),
                'badge' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
            ],
        ];
    }
}
