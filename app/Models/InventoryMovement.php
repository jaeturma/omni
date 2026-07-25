<?php

namespace App\Models;

use App\Enums\InventoryMovementStatus;
use App\Enums\InventoryMovementType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * @property int $product_service_id
 * @property int $warehouse_id
 * @property InventoryMovementType $type
 * @property Carbon $movement_date
 * @property numeric-string $quantity
 * @property numeric-string $unit_cost
 * @property numeric-string $total_cost
 * @property numeric-string|null $balance_quantity_before
 * @property numeric-string|null $balance_average_cost_before
 * @property numeric-string|null $balance_quantity_after
 * @property numeric-string|null $balance_average_cost_after
 * @property int|null $inventory_opening_balance_line_id
 * @property int|null $receiving_record_line_id
 * @property int|null $delivery_line_id
 * @property int|null $inventory_adjustment_line_id
 * @property int|null $inventory_transfer_line_id
 * @property int|null $physical_count_line_id
 * @property int|null $reversal_of_id
 * @property ProductService $product
 * @property Warehouse $warehouse
 */
#[Fillable(['inventory_opening_balance_line_id', 'receiving_record_line_id', 'delivery_line_id', 'inventory_adjustment_line_id', 'inventory_transfer_line_id', 'physical_count_line_id', 'reversal_of_id', 'product_service_id', 'warehouse_id', 'type', 'movement_date', 'quantity', 'unit_cost', 'total_cost', 'balance_quantity_before', 'balance_average_cost_before', 'balance_quantity_after', 'balance_average_cost_after', 'status', 'posted_at', 'posted_by', 'created_by'])]
class InventoryMovement extends Model
{
    protected $attributes = ['status' => 'posted'];

    protected static function booted(): void
    {
        $immutable = fn () => throw new LogicException('Posted inventory movements are append-only.');
        static::updating($immutable);
        static::deleting($immutable);
    }

    public function openingBalanceLine(): BelongsTo
    {
        return $this->belongsTo(InventoryOpeningBalanceLine::class, 'inventory_opening_balance_line_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(ProductService::class, 'product_service_id');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    /** @return BelongsTo<self, $this> */
    public function reversalOf(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reversal_of_id');
    }

    public function receivingRecordLine(): BelongsTo
    {
        return $this->belongsTo(ReceivingRecordLine::class);
    }

    public function deliveryLine(): BelongsTo
    {
        return $this->belongsTo(DeliveryLine::class);
    }

    public function adjustmentLine(): BelongsTo
    {
        return $this->belongsTo(InventoryAdjustmentLine::class, 'inventory_adjustment_line_id');
    }

    public function transferLine(): BelongsTo
    {
        return $this->belongsTo(InventoryTransferLine::class, 'inventory_transfer_line_id');
    }

    public function physicalCountLine(): BelongsTo
    {
        return $this->belongsTo(PhysicalCountLine::class);
    }

    protected function casts(): array
    {
        return ['type' => InventoryMovementType::class, 'movement_date' => 'date', 'quantity' => 'decimal:4',
            'unit_cost' => 'decimal:4', 'total_cost' => 'decimal:4', 'balance_quantity_before' => 'decimal:4',
            'balance_average_cost_before' => 'decimal:4', 'balance_quantity_after' => 'decimal:4',
            'balance_average_cost_after' => 'decimal:4', 'status' => InventoryMovementStatus::class, 'posted_at' => 'datetime'];
    }
}
