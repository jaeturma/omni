<?php

namespace App\Models;

use Database\Factories\FiscalYearFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['business_profile_id', 'name', 'starts_on', 'ends_on', 'is_current', 'created_by'])]
class FiscalYear extends Model
{
    /** @use HasFactory<FiscalYearFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::saving(fn (FiscalYear $year) => $year->current_marker = $year->is_current ? 1 : null);
    }

    public function businessProfile(): BelongsTo
    {
        return $this->belongsTo(BusinessProfile::class);
    }

    public function periods(): HasMany
    {
        return $this->hasMany(FiscalPeriod::class)->orderBy('starts_on');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function documentSequences(): HasMany
    {
        return $this->hasMany(DocumentSequence::class);
    }

    protected function casts(): array
    {
        return ['starts_on' => 'date', 'ends_on' => 'date', 'is_current' => 'boolean'];
    }
}
