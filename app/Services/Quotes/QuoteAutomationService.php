<?php

namespace App\Services\Quotes;

use App\Models\AppSetting;
use App\Models\Quote;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class QuoteAutomationService
{
    /**
     * @param  array<string, mixed>  $quoteData
     */
    public function createFromStructuredData(array $quoteData): Quote
    {
        $clientName = trim((string) ($quoteData['client_name'] ?? ''));
        $itemsInput = is_array($quoteData['items'] ?? null) ? $quoteData['items'] : [];

        if ($clientName === '') {
            throw new InvalidArgumentException('El cliente es obligatorio para crear la cotizacion.');
        }

        $items = $this->normalizeItems($itemsInput);

        if ($items === []) {
            throw new InvalidArgumentException('Debes incluir al menos un concepto válido.');
        }

        $issuedAt = $this->resolveIssuedAt((string) ($quoteData['issued_at'] ?? ''));

        /** @var Quote $quote */
        $quote = DB::transaction(function () use ($quoteData, $items, $clientName, $issuedAt) {
            $quote = Quote::create([
                'folio' => 'TMP-'.Str::uuid(),
                'reference_code' => $this->nullableString($quoteData['reference_code'] ?? null, 120),
                'client_name' => Str::limit($clientName, 255, ''),
                'client_rfc' => $this->nullableString($quoteData['client_rfc'] ?? null, 30),
                'location' => $this->nullableString($quoteData['location'] ?? null, 120),
                'issued_at' => $issuedAt,
                'currency' => 'MXN',
                'vat_rate' => 0,
                'terms' => $this->nullableString($quoteData['terms'] ?? null, 65535),
                'contact_phone' => $this->nullableString($quoteData['contact_phone'] ?? null, 60),
                'contact_email' => $this->nullableString($quoteData['contact_email'] ?? null, 255),
                'contact_name' => $this->nullableString($quoteData['contact_name'] ?? null, 255),
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

        return $quote;
    }

    public function buildPdfForQuote(Quote $quote): string
    {
        $quote->load([
            'items' => fn ($query) => $query->orderBy('position'),
            'payments' => fn ($query) => $query->orderBy('received_at')->orderBy('id'),
        ]);

        $logoPath = AppSetting::safeCurrent()->logoAbsolutePath();
        $outputDirectory = storage_path('app/private/telegram');

        if (!is_dir($outputDirectory)) {
            mkdir($outputDirectory, 0755, true);
        }

        $filePath = $outputDirectory.DIRECTORY_SEPARATOR.$this->telegramPdfFileBaseName($quote).'.pdf';

        Pdf::loadView('cotizaciones.pdf', [
            'quote' => $quote,
            'logoPath' => $logoPath,
        ])->setPaper('letter')->save($filePath);

        return $filePath;
    }

    private function telegramPdfFileBaseName(Quote $quote): string
    {
        $baseName = trim($quote->pdfFileBaseName());

        $baseName = preg_replace('/[\\\\\/\:\*\?"\<\>\|]+/u', ' ', $baseName) ?? '';
        $baseName = preg_replace('/\s+/u', ' ', trim($baseName)) ?? '';

        return $baseName !== '' ? $baseName : $quote->folio;
    }

    /**
     * @param  array<int, mixed>  $itemsInput
     * @return array<int, array<string, float|string>>
     */
    private function normalizeItems(array $itemsInput): array
    {
        $items = [];

        foreach ($itemsInput as $itemInput) {
            if (!is_array($itemInput)) {
                continue;
            }

            $description = trim((string) ($itemInput['description'] ?? ''));
            $quantity = (float) ($itemInput['quantity'] ?? 0);
            $unitPrice = (float) ($itemInput['unit_price'] ?? 0);

            if ($description === '' || $quantity <= 0 || $unitPrice < 0) {
                continue;
            }

            $items[] = [
                'description' => $description,
                'quantity' => round($quantity, 2),
                'unit_price' => round($unitPrice, 2),
                'line_total' => round($quantity * $unitPrice, 2),
            ];
        }

        return $items;
    }

    private function resolveIssuedAt(string $issuedAt): string
    {
        $trimmed = trim($issuedAt);

        if ($trimmed === '') {
            return now()->toDateString();
        }

        $timestamp = strtotime($trimmed);

        if ($timestamp === false) {
            return now()->toDateString();
        }

        return date('Y-m-d', $timestamp);
    }

    private function nullableString(mixed $value, int $maxLength, ?string $default = null): ?string
    {
        $string = trim((string) ($value ?? ''));

        if ($string === '') {
            $string = $default === null ? '' : trim($default);
        }

        if ($string === '') {
            return null;
        }

        return Str::limit($string, $maxLength, '');
    }
}
