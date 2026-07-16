<?php

namespace App\Models;

use Database\Factories\BusinessProfileFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'registered_business_name', 'trade_name', 'proprietor_name', 'tin', 'branch_code', 'rdo_code',
    'registration_date', 'business_start_date', 'registered_address', 'email', 'phone', 'website',
    'default_currency', 'timezone', 'fiscal_year_start_month', 'logo_path', 'active', 'created_by', 'updated_by',
])]
class BusinessProfile extends Model
{
    /** @use HasFactory<BusinessProfileFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::saving(fn (BusinessProfile $profile) => $profile->active_marker = $profile->active ? 1 : null);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('active', true);
    }

    public function taxProfile(): HasOne
    {
        return $this->hasOne(TaxProfile::class);
    }

    public function fiscalYears(): HasMany
    {
        return $this->hasMany(FiscalYear::class);
    }

    protected function casts(): array
    {
        return [
            'registration_date' => 'date',
            'business_start_date' => 'date',
            'fiscal_year_start_month' => 'integer',
            'active' => 'boolean',
        ];
    }
}
