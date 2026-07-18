<?php

namespace App\Models;

use App\Enums\PurchaseOrderStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** @property PurchaseOrderStatus $status */
#[Fillable(['purchase_request_id', 'canvass_quotation_id', 'supplier_id', 'document_number_reservation_id', 'purchase_order_number', 'order_date', 'expected_delivery_date', 'supplier_name', 'supplier_tin', 'supplier_address', 'delivery_location', 'supplier_quotation_reference', 'payment_terms', 'notes', 'document_discount_rate', 'subtotal', 'line_discount_total', 'document_discount_amount', 'freight', 'other_charges', 'grand_total', 'status', 'approved_at', 'approved_by', 'issued_at', 'issued_by', 'closed_at', 'closed_by', 'cancelled_at', 'cancelled_by', 'cancellation_reason', 'created_by', 'updated_by'])]
class PurchaseOrder extends Model
{
    protected $attributes = ['document_discount_rate' => 0, 'subtotal' => 0, 'line_discount_total' => 0, 'document_discount_amount' => 0, 'freight' => 0, 'other_charges' => 0, 'grand_total' => 0, 'status' => 'draft'];

    public function purchaseRequest(): BelongsTo
    {
        return $this->belongsTo(PurchaseRequest::class);
    }

    public function canvassQuotation(): BelongsTo
    {
        return $this->belongsTo(CanvassQuotation::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function numberReservation(): BelongsTo
    {
        return $this->belongsTo(DocumentNumberReservation::class, 'document_number_reservation_id');
    }

    /** @return HasMany<PurchaseOrderLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(PurchaseOrderLine::class)->orderBy('line_number');
    }

    protected function casts(): array
    {
        return ['order_date' => 'date', 'expected_delivery_date' => 'date', 'document_discount_rate' => 'decimal:6', 'subtotal' => 'decimal:4', 'line_discount_total' => 'decimal:4', 'document_discount_amount' => 'decimal:4', 'freight' => 'decimal:4', 'other_charges' => 'decimal:4', 'grand_total' => 'decimal:4', 'status' => PurchaseOrderStatus::class, 'approved_at' => 'datetime', 'issued_at' => 'datetime', 'closed_at' => 'datetime', 'cancelled_at' => 'datetime'];
    }
}
