<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** @property CarbonInterface $original_due_date @property CarbonInterface|null $adjusted_due_date */
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

    public function effectiveDueDate(): CarbonInterface
    {
        return $this->adjusted_due_date ?? $this->original_due_date;
    }

    protected function casts(): array
    {
        return ['original_due_date' => 'date', 'adjusted_due_date' => 'date', 'rule_snapshot' => 'array'];
    }
}
