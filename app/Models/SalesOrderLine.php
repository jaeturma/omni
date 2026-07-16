<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** @property numeric-string $ordered_quantity
 * @property numeric-string $delivered_quantity
 * @property numeric-string $invoiced_quantity
 * @property numeric-string $cancelled_quantity
 * @property numeric-string $remaining_quantity
 */
#[Fillable(['sales_order_id', 'quotation_line_id', 'product_service_id', 'line_number', 'item_type', 'sku', 'description', 'uom_code', 'uom_name', 'ordered_quantity', 'delivered_quantity', 'invoiced_quantity', 'cancelled_quantity', 'unit_price', 'discount_rate', 'gross_amount', 'discount_amount', 'net_amount'])]
class SalesOrderLine extends Model
{
    use HasFactory;

    public function salesOrder(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class);
    }

    public function productService(): BelongsTo
    {
        return $this->belongsTo(ProductService::class);
    }

    protected function remainingQuantity(): Attribute
    {
        return Attribute::get(fn (): string => bcsub(bcsub($this->ordered_quantity, $this->delivered_quantity, 4), $this->cancelled_quantity, 4));
    }

    protected function casts(): array
    {
        return ['line_number' => 'integer', 'ordered_quantity' => 'decimal:4', 'delivered_quantity' => 'decimal:4', 'invoiced_quantity' => 'decimal:4', 'cancelled_quantity' => 'decimal:4', 'unit_price' => 'decimal:4', 'discount_rate' => 'decimal:6', 'gross_amount' => 'decimal:4', 'discount_amount' => 'decimal:4', 'net_amount' => 'decimal:4'];
    }
}
