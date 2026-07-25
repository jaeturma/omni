<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

/**
 * @property int $product_service_id
 * @property numeric-string $system_quantity_snapshot
 * @property numeric-string|null $counted_quantity
 * @property numeric-string|null $variance_quantity
 * @property numeric-string $unit_cost_snapshot
 * @property numeric-string|null $variance_value
 */
#[Fillable(['physical_count_id', 'product_service_id', 'line_number', 'system_quantity_snapshot', 'counted_quantity', 'variance_quantity', 'unit_cost_snapshot', 'variance_value', 'explanation'])]
class PhysicalCountLine extends Model
{
    protected static function booted(): void
    {
        $guard = function (self $line): void {
            if ($line->count()->whereIn('status', ['approved', 'posted', 'voided'])->exists()) {
                throw new LogicException('Approved physical-count lines are immutable.');
            }
        };
        static::updating($guard);
        static::deleting($guard);
    }

    public function count(): BelongsTo
    {
        return $this->belongsTo(PhysicalCount::class, 'physical_count_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(ProductService::class, 'product_service_id');
    }

    /** @return HasMany<InventoryMovement, $this> */
    public function movements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class);
    }

    protected function casts(): array
    {
        return [
            'system_quantity_snapshot' => 'decimal:4', 'counted_quantity' => 'decimal:4',
            'variance_quantity' => 'decimal:4', 'unit_cost_snapshot' => 'decimal:4',
            'variance_value' => 'decimal:4',
        ];
    }
}
