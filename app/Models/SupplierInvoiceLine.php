<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** @property numeric-string $quantity @property numeric-string $unit_cost @property numeric-string $discount_rate @property numeric-string $gross_amount @property numeric-string $discount_amount @property numeric-string $net_amount */
#[Fillable(['supplier_invoice_id', 'purchase_order_line_id', 'receiving_record_line_id', 'line_number', 'item_type', 'sku', 'description', 'uom_code', 'uom_name', 'quantity', 'unit_cost', 'discount_rate', 'gross_amount', 'discount_amount', 'net_amount', 'notes'])]
class SupplierInvoiceLine extends Model
{
    public function supplierInvoice(): BelongsTo
    {
        return $this->belongsTo(SupplierInvoice::class);
    }

    public function purchaseOrderLine(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrderLine::class);
    }

    public function receivingRecordLine(): BelongsTo
    {
        return $this->belongsTo(ReceivingRecordLine::class);
    }

    protected function casts(): array
    {
        return ['line_number' => 'integer', 'quantity' => 'decimal:4', 'unit_cost' => 'decimal:4', 'discount_rate' => 'decimal:6', 'gross_amount' => 'decimal:4', 'discount_amount' => 'decimal:4', 'net_amount' => 'decimal:4'];
    }
}
