<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property CarbonInterface $last_reviewed_on
 * @property CarbonInterface|null $used_at
 */
class TaxComplianceRule extends Model
{
    protected $fillable = [
        'tax_profile_id', 'supersedes_id', 'tax_type', 'bir_form_number', 'form_title',
        'taxpayer_applicability', 'registration_applicability', 'filing_frequency',
        'effective_from', 'effective_to', 'tax_rate', 'tax_base_rule', 'credit_rule',
        'deadline_rule', 'amendment_supported', 'attachment_requirements',
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
            'amendment_supported' => 'boolean',
            'attachment_requirements' => 'array',
            'last_reviewed_on' => 'date',
            'used_at' => 'datetime',
            'active' => 'boolean',
        ];
    }
}
