<?php

namespace App\Models;

use App\Enums\InventoryAdjustmentStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * @property InventoryAdjustmentStatus $status
 * @property Carbon $adjustment_date
 */
#[Fillable(['document_number_reservation_id', 'adjustment_number', 'adjustment_date', 'fiscal_period_id', 'warehouse_id', 'type', 'inventory_adjustment_reason_id', 'explanation', 'status', 'approved_at', 'approved_by', 'posted_at', 'posted_by', 'voided_at', 'voided_by', 'void_reason', 'created_by', 'updated_by'])]
class InventoryAdjustment extends Model
{
    protected $attributes = ['status' => 'draft'];

    protected static function booted(): void
    {
        static::updating(function (self $adjustment): void {
            if ($adjustment->getRawOriginal('status') === InventoryAdjustmentStatus::Posted->value
                && array_diff(array_keys($adjustment->getDirty()), ['status', 'voided_at', 'voided_by', 'void_reason', 'updated_by', 'updated_at'])) {
                throw new LogicException('Posted inventory adjustments are immutable.');
            }
        });
    }

    /** @return HasMany<InventoryAdjustmentLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(InventoryAdjustmentLine::class)->orderBy('line_number');
    }

    public function fiscalPeriod(): BelongsTo
    {
        return $this->belongsTo(FiscalPeriod::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function reason(): BelongsTo
    {
        return $this->belongsTo(InventoryAdjustmentReason::class, 'inventory_adjustment_reason_id');
    }

    protected function casts(): array
    {
        return ['adjustment_date' => 'date', 'status' => InventoryAdjustmentStatus::class, 'approved_at' => 'datetime',
            'posted_at' => 'datetime', 'voided_at' => 'datetime'];
    }
}
