<?php

namespace App\Models;

use App\Enums\InventoryMovementStatus;
use App\Enums\InventoryMovementType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

#[Fillable(['inventory_opening_balance_line_id', 'reversal_of_id', 'product_service_id', 'warehouse_id', 'type', 'movement_date', 'quantity', 'unit_cost', 'total_cost', 'status', 'posted_at', 'posted_by', 'created_by'])]
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

    public function reversalOf(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reversal_of_id');
    }

    protected function casts(): array
    {
        return ['type' => InventoryMovementType::class, 'movement_date' => 'date', 'quantity' => 'decimal:4',
            'unit_cost' => 'decimal:4', 'total_cost' => 'decimal:4', 'status' => InventoryMovementStatus::class, 'posted_at' => 'datetime'];
    }
}
