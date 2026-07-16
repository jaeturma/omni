<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** @property numeric-string $quantity
 * @property numeric-string $unit_price
 */
#[Fillable(['sales_invoice_id', 'sales_order_line_id', 'delivery_line_id', 'product_service_id', 'line_number', 'item_type', 'sku', 'description', 'uom_code', 'uom_name', 'quantity', 'unit_price', 'discount_rate', 'gross_amount', 'discount_amount', 'net_amount'])]
class SalesInvoiceLine extends Model
{
    use HasFactory;

    public function salesInvoice(): BelongsTo
    {
        return $this->belongsTo(SalesInvoice::class);
    }

    public function salesOrderLine(): BelongsTo
    {
        return $this->belongsTo(SalesOrderLine::class);
    }

    public function deliveryLine(): BelongsTo
    {
        return $this->belongsTo(DeliveryLine::class);
    }

    protected function casts(): array
    {
        return ['line_number' => 'integer', 'quantity' => 'decimal:4', 'unit_price' => 'decimal:4', 'discount_rate' => 'decimal:6', 'gross_amount' => 'decimal:4', 'discount_amount' => 'decimal:4', 'net_amount' => 'decimal:4'];
    }
}
