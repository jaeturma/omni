<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property numeric-string $delivered_quantity
 */
#[Fillable(['delivery_id', 'sales_order_line_id', 'line_number', 'sku', 'description', 'uom_code', 'uom_name', 'delivered_quantity'])]
class DeliveryLine extends Model
{
    use HasFactory;

    public function delivery(): BelongsTo
    {
        return $this->belongsTo(Delivery::class);
    }

    /** @return BelongsTo<SalesOrderLine,$this> */
    public function salesOrderLine(): BelongsTo
    {
        return $this->belongsTo(SalesOrderLine::class);
    }

    /** @return HasMany<InventoryMovement, $this> */
    public function inventoryMovements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class);
    }

    protected function casts(): array
    {
        return ['line_number' => 'integer', 'delivered_quantity' => 'decimal:4'];
    }
}
