<?php

namespace App\Models;

use App\Enums\SalesOrderStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** @property SalesOrderStatus $status */
#[Fillable(['quotation_id', 'customer_id', 'document_number_reservation_id', 'sales_order_number', 'order_date', 'promised_delivery_date', 'customer_po_number', 'payment_terms', 'customer_name', 'customer_tin', 'billing_address', 'delivery_address', 'notes', 'document_discount_rate', 'subtotal', 'line_discount_total', 'document_discount_amount', 'grand_total', 'status', 'confirmed_at', 'confirmed_by', 'closed_at', 'closed_by', 'cancelled_at', 'cancelled_by', 'cancellation_reason', 'created_by', 'updated_by'])]
class SalesOrder extends Model
{
    use HasFactory;

    protected $attributes = ['payment_terms' => 0, 'document_discount_rate' => 0, 'subtotal' => 0, 'line_discount_total' => 0, 'document_discount_amount' => 0, 'grand_total' => 0, 'status' => 'draft'];

    public function quotation(): BelongsTo
    {
        return $this->belongsTo(Quotation::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /** @return HasMany<SalesOrderLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(SalesOrderLine::class)->orderBy('line_number');
    }

    /** @return HasMany<Delivery, $this> */
    public function deliveries(): HasMany
    {
        return $this->hasMany(Delivery::class);
    }

    protected function casts(): array
    {
        return ['order_date' => 'date', 'promised_delivery_date' => 'date', 'payment_terms' => 'integer', 'document_discount_rate' => 'decimal:6', 'subtotal' => 'decimal:4', 'line_discount_total' => 'decimal:4', 'document_discount_amount' => 'decimal:4', 'grand_total' => 'decimal:4', 'status' => SalesOrderStatus::class, 'confirmed_at' => 'datetime', 'closed_at' => 'datetime', 'cancelled_at' => 'datetime'];
    }
}
