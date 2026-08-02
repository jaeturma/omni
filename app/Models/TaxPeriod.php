<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property CarbonInterface $period_start
 * @property CarbonInterface $period_end
 * @property CarbonInterface $capture_start
 */
class TaxPeriod extends Model
{
    protected $fillable = ['tax_profile_id', 'frequency', 'period_start', 'period_end', 'capture_start', 'tax_year', 'quarter', 'label'];

    /** @return BelongsTo<TaxProfile, $this> */
    public function taxProfile(): BelongsTo
    {
        return $this->belongsTo(TaxProfile::class);
    }

    /** @return HasMany<TaxObligation, $this> */
    public function obligations(): HasMany
    {
        return $this->hasMany(TaxObligation::class);
    }

    /** @return HasMany<TaxReviewComment, $this> */
    public function reviewComments(): HasMany
    {
        return $this->hasMany(TaxReviewComment::class)->latest('id');
    }

    protected function casts(): array
    {
        return ['period_start' => 'date', 'period_end' => 'date', 'capture_start' => 'date', 'tax_year' => 'integer', 'quarter' => 'integer'];
    }
}
