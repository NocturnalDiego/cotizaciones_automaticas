<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreQuotePaymentRequest;
use App\Models\Quote;
use App\Models\QuotePayment;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Illuminate\Support\Facades\DB;

class QuotePaymentController extends Controller
{
    public function store(StoreQuotePaymentRequest $request, Quote $quote): RedirectResponse
    {
        DB::transaction(function () use ($request, $quote) {
            $quote->payments()->create($request->validated());
            $quote->recalculateTotals();
        });

        return redirect()
            ->route('cotizaciones.view', $quote)
            ->with('status', 'Anticipo registrado correctamente.');
    }

    public function edit(Quote $quote, QuotePayment $payment): View
    {
        $this->ensurePaymentBelongsToQuote($quote, $payment);

        return view('cotizaciones.anticipos.edit', [
            'quote' => $quote,
            'payment' => $payment,
        ]);
    }

    public function update(StoreQuotePaymentRequest $request, Quote $quote, QuotePayment $payment): RedirectResponse
    {
        $this->ensurePaymentBelongsToQuote($quote, $payment);

        DB::transaction(function () use ($request, $quote, $payment) {
            $payment->update($request->validated());
            $quote->recalculateTotals();
        });

        return redirect()
            ->route('cotizaciones.view', $quote)
            ->with('status', 'Anticipo actualizado correctamente.');
    }

    public function destroy(Request $request, Quote $quote, QuotePayment $payment): RedirectResponse
    {
        $this->ensurePaymentBelongsToQuote($quote, $payment);

        DB::transaction(function () use ($quote, $payment) {
            $payment->delete();
            $quote->recalculateTotals();
        });

        return redirect()
            ->route('cotizaciones.view', $quote)
            ->with('status', 'Anticipo eliminado correctamente.');
    }

    private function ensurePaymentBelongsToQuote(Quote $quote, QuotePayment $payment): void
    {
        abort_if($payment->quote_id !== $quote->id, 404);
    }
}
