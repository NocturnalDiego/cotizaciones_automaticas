<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreQuoteRequest;
use App\Http\Requests\UpdateQuoteRequest;
use App\Models\AppSetting;
use App\Models\Quote;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class QuoteController extends Controller
{
    public function index(): View
    {
        $quotes = Quote::query()
            ->latest('issued_at')
            ->latest('id')
            ->paginate(12);

        return view('cotizaciones.index', compact('quotes'));
    }

    public function create(): View
    {
        $quote = new Quote([
            'issued_at' => now()->toDateString(),
            'vat_rate' => 0,
            'currency' => 'MXN',
            'terms' => 'Estos costos se respetarán siempre y cuando se cuente con área libre y materiales disponibles.',
        ]);

        return view('cotizaciones.create', compact('quote'));
    }

    public function store(StoreQuoteRequest $request): RedirectResponse
    {
        $items = $this->buildItemsPayload(
            $request->input('item_description', []),
            $request->input('item_quantity', []),
            $request->input('item_unit_price', [])
        );

        $quote = DB::transaction(function () use ($request, $items) {
            $quote = Quote::create([
                'folio' => 'TMP-'.Str::uuid(),
                'reference_code' => $request->string('reference_code')->toString(),
                'client_name' => $request->string('client_name')->toString(),
                'client_rfc' => $request->string('client_rfc')->toString(),
                'location' => $request->string('location')->toString(),
                'issued_at' => $request->date('issued_at'),
                'currency' => 'MXN',
                'vat_rate' => 0,
                'terms' => $request->string('terms')->toString(),
                'contact_phone' => $request->string('contact_phone')->toString(),
                'contact_email' => $request->string('contact_email')->toString(),
                'contact_name' => $request->string('contact_name')->toString(),
            ]);

            $quote->update([
                'folio' => 'COT-'.str_pad((string) $quote->id, 6, '0', STR_PAD_LEFT),
            ]);

            foreach ($items as $index => $item) {
                $quote->items()->create([
                    ...$item,
                    'position' => $index + 1,
                ]);
            }

            $quote->recalculateTotals();

            return $quote;
        });

        return redirect()
            ->route('cotizaciones.view', $quote)
            ->with('status', 'Cotización creada correctamente.');
    }

    public function view(Quote $quote): View
    {
        $quote->load([
            'items' => fn ($query) => $query->orderBy('position'),
            'payments' => fn ($query) => $query->orderByDesc('received_at')->orderByDesc('id'),
        ]);

        return view('cotizaciones.view', compact('quote'));
    }

    public function edit(Quote $quote): View
    {
        $quote->load('items');

        return view('cotizaciones.edit', compact('quote'));
    }

    public function pdf(Quote $quote): Response
    {
        $quote->load([
            'items' => fn ($query) => $query->orderBy('position'),
            'payments' => fn ($query) => $query->orderBy('received_at')->orderBy('id'),
        ]);

        $logoPath = AppSetting::safeCurrent()->logoAbsolutePath();

        $pdf = Pdf::loadView('cotizaciones.pdf', [
            'quote' => $quote,
            'logoPath' => $logoPath,
        ])->setPaper('letter');

        return $pdf->download($quote->pdfFileBaseName().'.pdf');
    }

    public function update(UpdateQuoteRequest $request, Quote $quote): RedirectResponse
    {
        $items = $this->buildItemsPayload(
            $request->input('item_description', []),
            $request->input('item_quantity', []),
            $request->input('item_unit_price', [])
        );

        DB::transaction(function () use ($request, $quote, $items) {
            $quote->update([
                'reference_code' => $request->string('reference_code')->toString(),
                'client_name' => $request->string('client_name')->toString(),
                'client_rfc' => $request->string('client_rfc')->toString(),
                'location' => $request->string('location')->toString(),
                'issued_at' => $request->date('issued_at'),
                'vat_rate' => 0,
                'terms' => $request->string('terms')->toString(),
                'contact_phone' => $request->string('contact_phone')->toString(),
                'contact_email' => $request->string('contact_email')->toString(),
                'contact_name' => $request->string('contact_name')->toString(),
            ]);

            $quote->items()->delete();

            foreach ($items as $index => $item) {
                $quote->items()->create([
                    ...$item,
                    'position' => $index + 1,
                ]);
            }

            $quote->recalculateTotals();
        });

        return redirect()
            ->route('cotizaciones.view', $quote)
            ->with('status', 'Cotización actualizada correctamente.');
    }

    public function destroy(Quote $quote): RedirectResponse
    {
        $quote->delete();

        return redirect()
            ->route('cotizaciones.index')
            ->with('status', 'Cotización eliminada correctamente.');
    }

    /**
     * @param  array<int, mixed>  $descriptions
     * @param  array<int, mixed>  $quantities
     * @param  array<int, mixed>  $unitPrices
     * @return array<int, array<string, float|string>>
     */
    private function buildItemsPayload(array $descriptions, array $quantities, array $unitPrices): array
    {
        $items = [];

        foreach ($descriptions as $index => $description) {
            $cleanDescription = trim((string) $description);

            if ($cleanDescription === '') {
                continue;
            }

            $quantity = (float) ($quantities[$index] ?? 0);
            $unitPrice = (float) ($unitPrices[$index] ?? 0);

            if ($quantity <= 0 || $unitPrice < 0) {
                throw ValidationException::withMessages([
                    'item_description.'.$index => 'Cada concepto debe tener cantidad válida y precio unitario igual o mayor que cero.',
                ]);
            }

            $items[] = [
                'description' => $cleanDescription,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'line_total' => round($quantity * $unitPrice, 2),
            ];
        }

        if ($items === []) {
            throw ValidationException::withMessages([
                'item_description.0' => 'Debes capturar al menos un concepto para la cotización.',
            ]);
        }

        return $items;
    }
}
