<?php

namespace App\Models;

use Database\Factories\FiscalPeriodFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/** @property int $fiscal_year_id
 * @property string $status
 * @property Carbon $starts_on
 * @property Carbon $ends_on
 */
#[Fillable(['fiscal_year_id', 'name', 'starts_on', 'ends_on', 'calendar_year', 'calendar_month', 'calendar_quarter', 'status', 'closed_at', 'closed_by', 'close_notes', 'close_checklist', 'close_overrides', 'locked_at', 'locked_by', 'lock_notes', 'reopened_at', 'reopened_by', 'reopen_reason', 'lock_version'])]
class FiscalPeriod extends Model
{
    /** @use HasFactory<FiscalPeriodFactory> */
    use HasFactory;

    protected $attributes = ['status' => 'open'];

    /** @return BelongsTo<FiscalYear, $this> */
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

    public function reopenedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reopened_by');
    }

    public function events(): HasMany
    {
        return $this->hasMany(FiscalPeriodEvent::class)->latest('performed_at');
    }

    protected function casts(): array
    {
        return [
            'starts_on' => 'date', 'ends_on' => 'date', 'calendar_year' => 'integer',
            'calendar_month' => 'integer', 'calendar_quarter' => 'integer',
            'closed_at' => 'datetime', 'close_checklist' => 'array', 'close_overrides' => 'array',
            'locked_at' => 'datetime', 'reopened_at' => 'datetime', 'lock_version' => 'integer',
        ];
    }
}
