<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

/**
 * @property int $id
 * @property int $product_service_id
 * @property numeric-string $quantity
 * @property numeric-string|null $source_unit_cost
 * @property numeric-string|null $total_cost
 */
#[Fillable(['inventory_transfer_id', 'product_service_id', 'line_number', 'quantity', 'source_unit_cost', 'total_cost'])]
class InventoryTransferLine extends Model
{
    protected static function booted(): void
    {
        $guard = function (self $line): void {
            if ($line->transfer()->where('status', '!=', 'draft')->exists()) {
                throw new LogicException('Approved inventory-transfer lines are immutable.');
            }
        };
        static::updating($guard);
        static::deleting($guard);
    }

    public function transfer(): BelongsTo
    {
        return $this->belongsTo(InventoryTransfer::class, 'inventory_transfer_id');
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
        return ['quantity' => 'decimal:4', 'source_unit_cost' => 'decimal:4', 'total_cost' => 'decimal:4'];
    }
}
