<?php

namespace App\Models;

use DomainException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property numeric-string $gross_taxable_amount
 * @property numeric-string $excluded_amount
 * @property numeric-string $taxable_amount
 * @property numeric-string $tax_rate
 * @property numeric-string $gross_tax_due
 * @property numeric-string $allowable_credits
 * @property numeric-string $government_tax_withheld
 * @property numeric-string $prior_payment
 * @property numeric-string $surcharge
 * @property numeric-string $interest
 * @property numeric-string $compromise_penalty
 * @property numeric-string $total_amount_payable
 * @property int $revision_number
 */
class Bir2551qWorksheet extends Model
{
    protected $fillable = [
        'tax_obligation_id', 'tax_reconciliation_id', 'previous_revision_id', 'revision_number', 'return_type', 'basis_type',
        'return_year', 'quarter', 'status', 'filing_status', 'review_status', 'gross_taxable_amount', 'excluded_amount',
        'taxable_amount', 'tax_rate', 'gross_tax_due', 'allowable_credits', 'government_tax_withheld', 'prior_payment',
        'surcharge', 'interest', 'compromise_penalty', 'total_amount_payable', 'taxpayer_snapshot', 'rule_snapshot',
        'reconciliation_snapshot', 'source_snapshot', 'excluded_source_keys', 'exclusion_reason', 'exclusion_evidence',
        'credits_authority', 'credits_evidence', 'prior_payment_reference', 'penalty_authority', 'penalty_evidence',
        'preparation_notes', 'revision_reason', 'prepared_by', 'reviewed_at', 'reviewed_by', 'approved_at', 'approved_by', 'frozen_at',
    ];

    protected $attributes = ['return_type' => 'original', 'status' => 'draft', 'filing_status' => 'not_filed', 'review_status' => 'draft', 'excluded_amount' => 0, 'allowable_credits' => 0, 'government_tax_withheld' => 0, 'prior_payment' => 0, 'surcharge' => 0, 'interest' => 0, 'compromise_penalty' => 0];

    protected static function booted(): void
    {
        static::updating(function (self $worksheet): void {
            if ($worksheet->getRawOriginal('frozen_at') !== null) {
                throw new DomainException('Ready-to-file 2551Q worksheet revisions are immutable. Create a new revision instead.');
            }
        });
        static::deleting(fn (self $worksheet) => throw_if($worksheet->frozen_at !== null, DomainException::class, 'Frozen 2551Q worksheet revisions cannot be deleted.'));
    }

    /** @return BelongsTo<TaxObligation, $this> */
    public function taxObligation(): BelongsTo
    {
        return $this->belongsTo(TaxObligation::class);
    }

    /** @return BelongsTo<TaxReconciliation, $this> */
    public function taxReconciliation(): BelongsTo
    {
        return $this->belongsTo(TaxReconciliation::class);
    }

    /** @return BelongsTo<Bir2551qWorksheet, $this> */
    public function previousRevision(): BelongsTo
    {
        return $this->belongsTo(self::class, 'previous_revision_id');
    }

    /** @return HasMany<Bir2551qWorksheet, $this> */
    public function revisions(): HasMany
    {
        return $this->hasMany(self::class, 'previous_revision_id');
    }

    /** @return HasOne<TaxFiling, $this> */
    public function taxFiling(): HasOne
    {
        return $this->hasOne(TaxFiling::class);
    }

    protected function casts(): array
    {
        return ['gross_taxable_amount' => 'decimal:4', 'excluded_amount' => 'decimal:4', 'taxable_amount' => 'decimal:4', 'tax_rate' => 'decimal:6', 'gross_tax_due' => 'decimal:4', 'allowable_credits' => 'decimal:4', 'government_tax_withheld' => 'decimal:4', 'prior_payment' => 'decimal:4', 'surcharge' => 'decimal:4', 'interest' => 'decimal:4', 'compromise_penalty' => 'decimal:4', 'total_amount_payable' => 'decimal:4', 'taxpayer_snapshot' => 'array', 'rule_snapshot' => 'array', 'reconciliation_snapshot' => 'array', 'source_snapshot' => 'array', 'excluded_source_keys' => 'array', 'return_year' => 'integer', 'quarter' => 'integer', 'revision_number' => 'integer', 'reviewed_at' => 'datetime', 'approved_at' => 'datetime', 'frozen_at' => 'datetime'];
    }
}
