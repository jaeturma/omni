<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property numeric-string $received_quantity
 * @property numeric-string $accepted_quantity
 * @property numeric-string $rejected_quantity
 * @property numeric-string $credited_quantity
 */
#[Fillable(['receiving_record_id', 'purchase_order_line_id', 'line_number', 'item_type', 'sku', 'description', 'uom_code', 'uom_name', 'received_quantity', 'accepted_quantity', 'rejected_quantity', 'credited_quantity', 'rejection_reason', 'notes'])]
class ReceivingRecordLine extends Model
{
    public function receivingRecord(): BelongsTo
    {
        return $this->belongsTo(ReceivingRecord::class);
    }

    /** @return BelongsTo<PurchaseOrderLine, $this> */
    public function purchaseOrderLine(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrderLine::class);
    }

    /** @return HasMany<InventoryMovement, $this> */
    public function inventoryMovements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class);
    }

    protected function casts(): array
    {
        return ['line_number' => 'integer', 'received_quantity' => 'decimal:4', 'accepted_quantity' => 'decimal:4', 'rejected_quantity' => 'decimal:4', 'credited_quantity' => 'decimal:4'];
    }
}
