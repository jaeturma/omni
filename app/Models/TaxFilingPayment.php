<?php

namespace App\Models;

use DomainException;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property numeric-string $amount_paid
 * @property Carbon $payment_date
 * @property TaxFiling $taxFiling
 */
#[Fillable(['tax_filing_id', 'payment_channel', 'payment_date', 'payment_reference', 'amount_paid', 'bank_or_provider', 'notes', 'recorded_by'])]
class TaxFilingPayment extends Model
{
    protected static function booted(): void
    {
        static::updating(function (): void {
            throw new DomainException('Tax payment history is immutable.');
        });
        static::deleting(function (): void {
            throw new DomainException('Tax payment history cannot be deleted.');
        });
    }

    /** @return BelongsTo<TaxFiling, $this> */
    public function taxFiling(): BelongsTo
    {
        return $this->belongsTo(TaxFiling::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(TaxFilingAttachment::class);
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    protected function casts(): array
    {
        return ['payment_date' => 'date', 'amount_paid' => 'decimal:4'];
    }
}
