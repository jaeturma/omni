<?php

namespace App\Models;

use App\Enums\PurchaseRequestStatus;
use App\Models\Concerns\HasPurchasingAttachments;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/** @property PurchaseRequestStatus $status */
#[Fillable(['document_number_reservation_id', 'request_number', 'request_date', 'requested_by', 'needed_by', 'purpose', 'requesting_unit', 'project_reference', 'notes', 'estimated_total', 'status', 'submitted_at', 'submitted_by', 'approved_at', 'approved_by', 'rejected_at', 'rejected_by', 'rejection_reason', 'converted_at', 'converted_by', 'cancelled_at', 'cancelled_by', 'cancellation_reason', 'created_by', 'updated_by'])]
class PurchaseRequest extends Model
{
    use HasPurchasingAttachments;

    protected $attributes = ['estimated_total' => 0, 'status' => 'draft'];

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    /** @return HasMany<PurchaseRequestLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(PurchaseRequestLine::class)->orderBy('line_number');
    }

    /** @return HasMany<CanvassQuotation, $this> */
    public function canvassQuotations(): HasMany
    {
        return $this->hasMany(CanvassQuotation::class)->orderBy('quoted_amount');
    }

    public function numberReservation(): BelongsTo
    {
        return $this->belongsTo(DocumentNumberReservation::class, 'document_number_reservation_id');
    }

    public function purchaseOrder(): HasOne
    {
        return $this->hasOne(PurchaseOrder::class);
    }

    protected function casts(): array
    {
        return ['request_date' => 'date', 'needed_by' => 'date', 'estimated_total' => 'decimal:4', 'status' => PurchaseRequestStatus::class, 'submitted_at' => 'datetime', 'approved_at' => 'datetime', 'rejected_at' => 'datetime', 'converted_at' => 'datetime', 'cancelled_at' => 'datetime'];
    }
}
