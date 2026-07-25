<?php

namespace App\Models;

use App\Enums\InventoryOpeningStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * @property InventoryOpeningStatus $status
 * @property Carbon $opening_date
 */
#[Fillable(['document_number_reservation_id', 'batch_number', 'opening_date', 'fiscal_period_id', 'warehouse_id', 'reference', 'notes', 'status', 'posted_at', 'posted_by', 'voided_at', 'voided_by', 'void_reason', 'created_by', 'updated_by'])]
class InventoryOpeningBalance extends Model
{
    protected $attributes = ['status' => 'draft'];

    protected static function booted(): void
    {
        static::updating(function (self $opening): void {
            if ($opening->getRawOriginal('status') === InventoryOpeningStatus::Posted->value
                && array_diff(array_keys($opening->getDirty()), ['status', 'voided_at', 'voided_by', 'void_reason', 'updated_by', 'updated_at'])) {
                throw new LogicException('Posted inventory opening balances are immutable.');
            }
        });
    }

    /** @return HasMany<InventoryOpeningBalanceLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(InventoryOpeningBalanceLine::class)->orderBy('line_number');
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
        return ['opening_date' => 'date', 'status' => InventoryOpeningStatus::class, 'posted_at' => 'datetime', 'voided_at' => 'datetime'];
    }
}
