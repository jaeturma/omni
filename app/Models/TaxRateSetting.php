<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** @property numeric-string $rate */
class TaxRateSetting extends Model
{
    protected $fillable = ['tax_profile_id', 'tax_type', 'rate', 'effective_from', 'effective_to', 'active'];

    public function taxProfile(): BelongsTo
    {
        return $this->belongsTo(TaxProfile::class);
    }

    protected function casts(): array
    {
        return ['rate' => 'decimal:6', 'effective_from' => 'date', 'effective_to' => 'date', 'active' => 'boolean'];
    }
}
