<?php

namespace App\Models;

use DomainException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property int $revision_number
 * @property numeric-string $taxable_income
 * @property numeric-string $income_tax_due
 * @property numeric-string $total_amount_payable
 */
class Bir1701qWorksheet extends Model
{
    protected $fillable = ['tax_obligation_id', 'previous_revision_id', 'revision_number', 'return_type', 'taxable_year', 'quarter', 'income_tax_method', 'deduction_method', 'status', 'filing_status', 'review_status', 'cumulative_gross_sales', 'sales_returns_discounts', 'net_sales', 'cost_of_sales', 'other_income', 'gross_income', 'financial_itemized_deductions', 'osd_deduction', 'manual_deduction_adjustment', 'taxable_income_adjustment', 'taxable_income', 'income_tax_due', 'prior_quarter_taxable_income', 'prior_quarter_income_tax_due', 'prior_quarter_payments', 'verified_creditable_withholding', 'manual_creditable_withholding', 'other_allowable_credits', 'surcharge', 'interest', 'compromise_penalty', 'total_amount_payable', 'taxpayer_snapshot', 'rule_snapshot', 'financial_report_snapshot', 'withholding_snapshot', 'prior_quarter_snapshot', 'manual_adjustment_reason', 'manual_adjustment_evidence', 'prior_payment_evidence', 'withholding_evidence', 'other_credits_authority', 'other_credits_evidence', 'penalty_authority', 'penalty_evidence', 'preparation_notes', 'revision_reason', 'prepared_by', 'reviewed_at', 'reviewed_by', 'approved_at', 'approved_by', 'frozen_at'];

    protected $attributes = ['return_type' => 'original', 'status' => 'draft', 'filing_status' => 'not_filed', 'review_status' => 'draft', 'manual_deduction_adjustment' => 0, 'taxable_income_adjustment' => 0, 'prior_quarter_payments' => 0, 'manual_creditable_withholding' => 0, 'other_allowable_credits' => 0, 'surcharge' => 0, 'interest' => 0, 'compromise_penalty' => 0];

    protected static function booted(): void
    {
        static::updating(function (self $worksheet): void {
            throw_if($worksheet->getRawOriginal('frozen_at') !== null, DomainException::class, 'Ready-to-file 1701Q worksheet revisions are immutable. Create a new revision instead.');
        });
        static::deleting(function (self $worksheet): void {
            throw_if($worksheet->frozen_at !== null, DomainException::class, 'Frozen 1701Q worksheet revisions cannot be deleted.');
        });
    }

    /** @return BelongsTo<TaxObligation, $this> */
    public function taxObligation(): BelongsTo
    {
        return $this->belongsTo(TaxObligation::class);
    }

    /** @return BelongsTo<Bir1701qWorksheet, $this> */
    public function previousRevision(): BelongsTo
    {
        return $this->belongsTo(self::class, 'previous_revision_id');
    }

    /** @return HasMany<Bir1701qWorksheet, $this> */
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
        $money = ['cumulative_gross_sales', 'sales_returns_discounts', 'net_sales', 'cost_of_sales', 'other_income', 'gross_income', 'financial_itemized_deductions', 'osd_deduction', 'manual_deduction_adjustment', 'taxable_income_adjustment', 'taxable_income', 'income_tax_due', 'prior_quarter_taxable_income', 'prior_quarter_income_tax_due', 'prior_quarter_payments', 'verified_creditable_withholding', 'manual_creditable_withholding', 'other_allowable_credits', 'surcharge', 'interest', 'compromise_penalty', 'total_amount_payable'];

        return array_fill_keys($money, 'decimal:4') + ['taxpayer_snapshot' => 'array', 'rule_snapshot' => 'array', 'financial_report_snapshot' => 'array', 'withholding_snapshot' => 'array', 'prior_quarter_snapshot' => 'array', 'revision_number' => 'integer', 'taxable_year' => 'integer', 'quarter' => 'integer', 'reviewed_at' => 'datetime', 'approved_at' => 'datetime', 'frozen_at' => 'datetime'];
    }
}
