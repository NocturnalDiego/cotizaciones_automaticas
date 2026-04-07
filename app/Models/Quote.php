<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Model;

class Quote extends Model
{
    use HasFactory;

    public const STATUS_EMITIDA = 'emitida';
    public const STATUS_CON_ANTICIPO = 'con_anticipo';
    public const STATUS_PAGADA = 'pagada';

    protected $fillable = [
        'folio',
        'reference_code',
        'client_name',
        'client_rfc',
        'location',
        'issued_at',
        'currency',
        'vat_rate',
        'subtotal',
        'vat_amount',
        'total',
        'paid_total',
        'balance_due',
        'status',
        'terms',
        'contact_id',
        'contact_phone',
        'contact_email',
        'contact_name',
    ];

    protected function casts(): array
    {
        return [
            'issued_at' => 'date',
            'vat_rate' => 'decimal:2',
            'subtotal' => 'decimal:2',
            'vat_amount' => 'decimal:2',
            'total' => 'decimal:2',
            'paid_total' => 'decimal:2',
            'balance_due' => 'decimal:2',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(QuoteItem::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(QuotePayment::class);
    }

    public function recalculateTotals(): void
    {
        $subtotal = (float) $this->items()->sum('line_total');
        $vatAmount = 0.0;
        $total = round($subtotal, 2);
        $paidTotal = (float) $this->payments()->sum('amount');
        $balanceDue = round(max($total - $paidTotal, 0), 2);

        if ($paidTotal <= 0) {
            $status = self::STATUS_EMITIDA;
        } elseif ($balanceDue <= 0) {
            $status = self::STATUS_PAGADA;
        } else {
            $status = self::STATUS_CON_ANTICIPO;
        }

        $this->update([
            'subtotal' => $subtotal,
            'vat_amount' => $vatAmount,
            'total' => $total,
            'paid_total' => $paidTotal,
            'balance_due' => $balanceDue,
            'status' => $status,
        ]);
    }

    public function pdfFileBaseName(): string
    {
        $clientName = trim((string) $this->client_name);
        $referenceCode = trim((string) $this->reference_code);

        $parts = array_values(array_filter([
            $clientName,
            $referenceCode,
        ], fn (string $value): bool => $value !== ''));

        if ($parts === []) {
            return $this->folio;
        }

        return implode(' - ', $parts);
    }
}
