<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property CarbonInterface $last_reviewed_on
 * @property CarbonInterface|null $used_at
 * @property CarbonInterface $effective_from
 * @property CarbonInterface|null $effective_to
 * @property array<int, int>|null $applicable_quarters
 * @property int $deadline_months_after_period_end
 * @property int $deadline_day
 * @property int $id
 * @property numeric-string|null $tax_rate
 * @property array<string, mixed>|null $calculation_parameters
 */
class TaxComplianceRule extends Model
{
    protected $fillable = [
        'tax_profile_id', 'supersedes_id', 'tax_type', 'bir_form_number', 'form_title',
        'taxpayer_applicability', 'registration_applicability', 'filing_frequency', 'applicable_quarters',
        'effective_from', 'effective_to', 'tax_rate', 'tax_base_rule', 'credit_rule',
        'calculation_parameters',
        'deadline_rule', 'deadline_months_after_period_end', 'deadline_day', 'amendment_supported', 'attachment_requirements',
        'official_reference_title', 'official_reference_url', 'last_reviewed_on',
        'reviewed_by', 'reviewer_notes', 'change_reason', 'used_at', 'active',
    ];

    protected $attributes = [
        'registration_applicability' => 'any',
        'amendment_supported' => false,
        'active' => true,
    ];

    /** @return BelongsTo<TaxProfile, $this> */
    public function taxProfile(): BelongsTo
    {
        return $this->belongsTo(TaxProfile::class);
    }

    /** @return BelongsTo<User, $this> */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /** @return BelongsTo<TaxComplianceRule, $this> */
    public function supersedes(): BelongsTo
    {
        return $this->belongsTo(self::class, 'supersedes_id');
    }

    /** @return HasMany<TaxObligation, $this> */
    public function obligations(): HasMany
    {
        return $this->hasMany(TaxObligation::class);
    }

    public function scopeActiveOn(Builder $query, string $date): Builder
    {
        return $query->where('active', true)
            ->whereDate('effective_from', '<=', $date)
            ->where(fn (Builder $query): Builder => $query
                ->whereNull('effective_to')
                ->orWhereDate('effective_to', '>=', $date));
    }

    public function referenceReviewIsStale(): bool
    {
        return $this->last_reviewed_on->lt(
            CarbonImmutable::today()->subDays((int) config('tax_compliance.reference_review_days')),
        );
    }

    protected function casts(): array
    {
        return [
            'effective_from' => 'date',
            'effective_to' => 'date',
            'tax_rate' => 'decimal:6',
            'calculation_parameters' => 'array',
            'applicable_quarters' => 'array',
            'deadline_months_after_period_end' => 'integer',
            'deadline_day' => 'integer',
            'amendment_supported' => 'boolean',
            'attachment_requirements' => 'array',
            'last_reviewed_on' => 'date',
            'used_at' => 'datetime',
            'active' => 'boolean',
        ];
    }
}
