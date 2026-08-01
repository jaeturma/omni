<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** @property CarbonInterface $registration_start_date */
class TaxProfile extends Model
{
    protected $fillable = ['business_profile_id', 'taxpayer_type', 'registration_type', 'vat_status', 'income_tax_option', 'percentage_tax_registered', 'percentage_tax_rate', 'percentage_tax_effective_from', 'percentage_tax_effective_to', 'filing_frequency', 'registration_start_date', 'first_filing_period', 'rdo_code', 'tin', 'branch_code', 'registered_books_type', 'notes', 'active'];

    protected static function booted(): void
    {
        static::saving(fn (self $model) => $model->active_marker = $model->active ? 1 : null);
    }

    public function businessProfile(): BelongsTo
    {
        return $this->belongsTo(BusinessProfile::class);
    }

    public function rates(): HasMany
    {
        return $this->hasMany(TaxRateSetting::class);
    }

    public function forms(): HasMany
    {
        return $this->hasMany(TaxFormRegistration::class);
    }

    /** @return HasMany<TaxComplianceRule, $this> */
    public function complianceRules(): HasMany
    {
        return $this->hasMany(TaxComplianceRule::class);
    }

    /** @return HasMany<TaxPeriod, $this> */
    public function taxPeriods(): HasMany
    {
        return $this->hasMany(TaxPeriod::class);
    }

    protected function casts(): array
    {
        return ['percentage_tax_registered' => 'boolean', 'percentage_tax_rate' => 'decimal:6', 'percentage_tax_effective_from' => 'date', 'percentage_tax_effective_to' => 'date', 'registration_start_date' => 'date', 'active' => 'boolean'];
    }
}
