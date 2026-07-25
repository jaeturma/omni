<?php

namespace App\Models;

use App\Enums\PhysicalCountStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * @property PhysicalCountStatus $status
 * @property Carbon $count_date
 * @property Carbon $cutoff_at
 * @property bool $blind_count
 */
#[Fillable(['document_number_reservation_id', 'count_number', 'count_date', 'fiscal_period_id', 'warehouse_id', 'cutoff_at', 'blind_count', 'notes', 'status', 'counting_started_at', 'counting_started_by', 'counted_by', 'submitted_at', 'submitted_by', 'reviewed_at', 'reviewed_by', 'approved_at', 'approved_by', 'posted_at', 'posted_by', 'voided_at', 'voided_by', 'void_reason', 'created_by', 'updated_by'])]
class PhysicalCount extends Model
{
    protected $attributes = ['blind_count' => false, 'status' => 'draft'];

    protected static function booted(): void
    {
        static::updating(function (self $count): void {
            if ($count->getRawOriginal('status') === PhysicalCountStatus::Posted->value
                && array_diff(array_keys($count->getDirty()), ['status', 'voided_at', 'voided_by', 'void_reason', 'updated_by', 'updated_at'])) {
                throw new LogicException('Posted physical counts are immutable.');
            }
        });
    }

    /** @return HasMany<PhysicalCountLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(PhysicalCountLine::class)->orderBy('line_number');
    }

    public function fiscalPeriod(): BelongsTo
    {
        return $this->belongsTo(FiscalPeriod::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    protected function casts(): array
    {
        return [
            'count_date' => 'date', 'cutoff_at' => 'datetime', 'blind_count' => 'boolean',
            'status' => PhysicalCountStatus::class, 'counting_started_at' => 'datetime',
            'submitted_at' => 'datetime', 'reviewed_at' => 'datetime', 'approved_at' => 'datetime',
            'posted_at' => 'datetime', 'voided_at' => 'datetime',
        ];
    }
}
