<?php

namespace App\Models;

use App\Enums\QuotationStatus;
use Database\Factories\QuotationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/** @property QuotationStatus $status */
#[Fillable(['customer_id', 'document_number_reservation_id', 'quotation_number', 'quotation_date', 'valid_until', 'customer_name', 'customer_tin', 'contact_name', 'contact_email', 'contact_phone', 'billing_address', 'delivery_address', 'reference', 'notes', 'terms_and_conditions', 'document_discount_rate', 'subtotal', 'line_discount_total', 'document_discount_amount', 'grand_total', 'status', 'submitted_at', 'submitted_by', 'approved_at', 'approved_by', 'rejected_at', 'rejected_by', 'rejection_reason', 'expired_at', 'expired_by', 'converted_at', 'converted_by', 'cancelled_at', 'cancelled_by', 'cancellation_reason', 'created_by', 'updated_by'])]
class Quotation extends Model
{
    /** @use HasFactory<QuotationFactory> */
    use HasFactory;

    protected $attributes = ['document_discount_rate' => 0, 'subtotal' => 0, 'line_discount_total' => 0, 'document_discount_amount' => 0, 'grand_total' => 0, 'status' => 'draft'];

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function numberReservation(): BelongsTo
    {
        return $this->belongsTo(DocumentNumberReservation::class, 'document_number_reservation_id');
    }

    /** @return HasMany<QuotationLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(QuotationLine::class)->orderBy('line_number');
    }

    public function salesOrder(): HasOne
    {
        return $this->hasOne(SalesOrder::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    protected function casts(): array
    {
        return ['quotation_date' => 'date', 'valid_until' => 'date', 'document_discount_rate' => 'decimal:6', 'subtotal' => 'decimal:4', 'line_discount_total' => 'decimal:4', 'document_discount_amount' => 'decimal:4', 'grand_total' => 'decimal:4', 'status' => QuotationStatus::class, 'submitted_at' => 'datetime', 'approved_at' => 'datetime', 'rejected_at' => 'datetime', 'expired_at' => 'datetime', 'converted_at' => 'datetime', 'cancelled_at' => 'datetime'];
    }
}
