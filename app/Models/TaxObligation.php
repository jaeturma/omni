<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property CarbonInterface $original_due_date
 * @property CarbonInterface|null $adjusted_due_date
 * @property TaxReconciliation|null $reconciliation
 */
class TaxObligation extends Model
{
    protected $fillable = [
        'tax_period_id', 'tax_compliance_rule_id', 'tax_type', 'bir_form_number',
        'original_due_date', 'adjusted_due_date', 'deadline_rule_source', 'status',
        'filing_status', 'payment_status', 'amendment_status', 'assigned_reviewer_id',
        'notes', 'rule_snapshot',
    ];

    protected $attributes = ['status' => 'upcoming', 'filing_status' => 'not_filed', 'payment_status' => 'unpaid', 'amendment_status' => 'original'];

    /** @return BelongsTo<TaxPeriod, $this> */
    public function taxPeriod(): BelongsTo
    {
        return $this->belongsTo(TaxPeriod::class);
    }

    /** @return BelongsTo<TaxComplianceRule, $this> */
    public function taxComplianceRule(): BelongsTo
    {
        return $this->belongsTo(TaxComplianceRule::class);
    }

    /** @return BelongsTo<User, $this> */
    public function assignedReviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_reviewer_id');
    }

    /** @return HasMany<TaxObligationDeadlineAdjustment, $this> */
    public function deadlineAdjustments(): HasMany
    {
        return $this->hasMany(TaxObligationDeadlineAdjustment::class);
    }

    /** @return HasOne<TaxReconciliation, $this> */
    public function reconciliation(): HasOne
    {
        return $this->hasOne(TaxReconciliation::class);
    }

    /** @return HasMany<Bir2551qWorksheet, $this> */
    public function bir2551qWorksheets(): HasMany
    {
        return $this->hasMany(Bir2551qWorksheet::class)->latest('revision_number');
    }

    /** @return HasMany<Bir1701qWorksheet, $this> */
    public function bir1701qWorksheets(): HasMany
    {
        return $this->hasMany(Bir1701qWorksheet::class)->latest('revision_number');
    }

    /** @return HasMany<TaxFiling, $this> */
    public function taxFilings(): HasMany
    {
        return $this->hasMany(TaxFiling::class)->latest('filing_date')->latest('id');
    }

    public function effectiveDueDate(): CarbonInterface
    {
        return $this->adjusted_due_date ?? $this->original_due_date;
    }

    protected function casts(): array
    {
        return ['original_due_date' => 'date', 'adjusted_due_date' => 'date', 'rule_snapshot' => 'array'];
    }
}
