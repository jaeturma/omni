<?php

namespace App\Models;

use DomainException;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property numeric-string $worksheet_amount_payable
 * @property numeric-string $amount_declared
 */
#[Fillable(['tax_obligation_id', 'bir2551q_worksheet_id', 'bir1701q_worksheet_id', 'original_filing_id', 'bir_form_number', 'worksheet_revision', 'filing_channel', 'filing_date', 'return_reference', 'is_amended', 'amendment_reason', 'worksheet_amount_payable', 'amount_declared', 'declared_difference', 'confirmed_at', 'filed_by', 'reviewed_by', 'notes'])]
class TaxFiling extends Model
{
    protected static function booted(): void
    {
        static::updating(function (): void {
            throw new DomainException('Tax filing history is immutable. Record an amended filing instead.');
        });
        static::deleting(function (): void {
            throw new DomainException('Tax filing history cannot be deleted.');
        });
    }

    public function taxObligation(): BelongsTo
    {
        return $this->belongsTo(TaxObligation::class);
    }

    public function bir2551qWorksheet(): BelongsTo
    {
        return $this->belongsTo(Bir2551qWorksheet::class);
    }

    public function bir1701qWorksheet(): BelongsTo
    {
        return $this->belongsTo(Bir1701qWorksheet::class);
    }

    public function originalFiling(): BelongsTo
    {
        return $this->belongsTo(self::class, 'original_filing_id');
    }

    public function amendments(): HasMany
    {
        return $this->hasMany(self::class, 'original_filing_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(TaxFilingPayment::class)->orderBy('payment_date')->orderBy('id');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(TaxFilingAttachment::class)->orderBy('id');
    }

    public function filedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'filed_by');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /** @return numeric-string */
    public function amountPaid(): string
    {
        return (string) $this->payments()->sum('amount_paid');
    }

    /** @return numeric-string */
    public function paymentBalance(): string
    {
        return bcsub((string) $this->getRawOriginal('amount_declared'), $this->amountPaid(), 4);
    }

    public function paymentStatus(): string
    {
        $comparison = bccomp($this->amountPaid(), (string) $this->getRawOriginal('amount_declared'), 4);

        return match (true) {
            $comparison === 1 => 'overpaid', $comparison === 0 => 'paid', bccomp($this->amountPaid(), '0.0000', 4) === 1 => 'partially_paid', default => 'unpaid'
        };
    }

    protected function casts(): array
    {
        return ['filing_date' => 'date', 'is_amended' => 'boolean', 'worksheet_amount_payable' => 'decimal:4', 'amount_declared' => 'decimal:4', 'declared_difference' => 'decimal:4', 'confirmed_at' => 'datetime', 'worksheet_revision' => 'integer'];
    }
}
