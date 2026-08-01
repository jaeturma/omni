<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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

    protected function casts(): array
    {
        return ['period_start' => 'date', 'period_end' => 'date', 'capture_start' => 'date', 'tax_year' => 'integer', 'quarter' => 'integer'];
    }
}
