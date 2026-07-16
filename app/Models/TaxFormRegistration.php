<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaxFormRegistration extends Model
{
    protected $fillable = ['tax_profile_id', 'form_code', 'filing_frequency', 'active'];

    public function taxProfile(): BelongsTo
    {
        return $this->belongsTo(TaxProfile::class);
    }

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }
}
