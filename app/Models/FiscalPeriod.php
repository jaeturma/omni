<?php

namespace App\Models;

use Database\Factories\FiscalPeriodFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['fiscal_year_id', 'name', 'starts_on', 'ends_on', 'calendar_year', 'calendar_month', 'calendar_quarter', 'status', 'closed_at', 'closed_by', 'locked_at', 'locked_by'])]
class FiscalPeriod extends Model
{
    /** @use HasFactory<FiscalPeriodFactory> */
    use HasFactory;

    protected $attributes = ['status' => 'open'];

    public function fiscalYear(): BelongsTo
    {
        return $this->belongsTo(FiscalYear::class);
    }

    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function lockedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'locked_by');
    }

    protected function casts(): array
    {
        return ['starts_on' => 'date', 'ends_on' => 'date', 'calendar_year' => 'integer', 'calendar_month' => 'integer', 'calendar_quarter' => 'integer', 'closed_at' => 'datetime', 'locked_at' => 'datetime'];
    }
}
