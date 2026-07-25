<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** @property int|null $product_service_id
 * @property numeric-string $unit_cost
 * @property numeric-string $ordered_quantity
 * @property numeric-string $received_quantity
 * @property numeric-string $billed_quantity
 * @property numeric-string $cancelled_quantity
 */
#[Fillable(['purchase_order_id', 'purchase_request_line_id', 'product_service_id', 'line_number', 'item_type', 'sku', 'description', 'uom_code', 'uom_name', 'ordered_quantity', 'received_quantity', 'billed_quantity', 'cancelled_quantity', 'unit_cost', 'discount_rate', 'gross_amount', 'discount_amount', 'net_amount'])]
class PurchaseOrderLine extends Model
{
    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function purchaseRequestLine(): BelongsTo
    {
        return $this->belongsTo(PurchaseRequestLine::class);
    }

    public function productService(): BelongsTo
    {
        return $this->belongsTo(ProductService::class);
    }

    protected function remainingToReceive(): Attribute
    {
        return Attribute::get(fn (): string => bcsub(bcsub($this->ordered_quantity, $this->received_quantity, 4), $this->cancelled_quantity, 4));
    }

    protected function remainingToBill(): Attribute
    {
        return Attribute::get(fn (): string => bcsub(bcsub($this->ordered_quantity, $this->billed_quantity, 4), $this->cancelled_quantity, 4));
    }

    protected function casts(): array
    {
        return ['line_number' => 'integer', 'ordered_quantity' => 'decimal:4', 'received_quantity' => 'decimal:4', 'billed_quantity' => 'decimal:4', 'cancelled_quantity' => 'decimal:4', 'unit_cost' => 'decimal:4', 'discount_rate' => 'decimal:6', 'gross_amount' => 'decimal:4', 'discount_amount' => 'decimal:4', 'net_amount' => 'decimal:4'];
    }
}
