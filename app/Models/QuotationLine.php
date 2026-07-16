<?php

namespace App\Models;

use Database\Factories\QuotationLineFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** @property numeric-string $quantity */
#[Fillable(['quotation_id', 'product_service_id', 'line_number', 'item_type', 'sku', 'description', 'uom_code', 'uom_name', 'quantity', 'unit_price', 'discount_rate', 'gross_amount', 'discount_amount', 'net_amount'])]
class QuotationLine extends Model
{
    /** @use HasFactory<QuotationLineFactory> */
    use HasFactory;

    public function quotation(): BelongsTo
    {
        return $this->belongsTo(Quotation::class);
    }

    public function productService(): BelongsTo
    {
        return $this->belongsTo(ProductService::class);
    }

    protected function casts(): array
    {
        return ['line_number' => 'integer', 'quantity' => 'decimal:4', 'unit_price' => 'decimal:4', 'discount_rate' => 'decimal:6', 'gross_amount' => 'decimal:4', 'discount_amount' => 'decimal:4', 'net_amount' => 'decimal:4'];
    }
}
