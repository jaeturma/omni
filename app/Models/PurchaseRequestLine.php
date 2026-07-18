<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['purchase_request_id', 'product_service_id', 'preferred_supplier_id', 'line_number', 'item_type', 'sku', 'description', 'uom_code', 'uom_name', 'quantity', 'estimated_unit_cost', 'estimated_total', 'notes'])]
class PurchaseRequestLine extends Model
{
    public function purchaseRequest(): BelongsTo
    {
        return $this->belongsTo(PurchaseRequest::class);
    }

    public function productService(): BelongsTo
    {
        return $this->belongsTo(ProductService::class);
    }

    public function preferredSupplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'preferred_supplier_id');
    }

    protected function casts(): array
    {
        return ['line_number' => 'integer', 'quantity' => 'decimal:4', 'estimated_unit_cost' => 'decimal:4', 'estimated_total' => 'decimal:4'];
    }
}
