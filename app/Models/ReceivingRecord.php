<?php

namespace App\Models;

use App\Enums\ReceivingStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** @property ReceivingStatus $status */
#[Fillable(['purchase_order_id', 'supplier_id', 'warehouse_id', 'document_number_reservation_id', 'receiving_number', 'receiving_date', 'supplier_name', 'delivery_location', 'delivery_receipt_number', 'supplier_invoice_reference', 'inspection_reference', 'received_by', 'inspected_by', 'accepted_by', 'notes', 'status', 'purchase_order_status_before_receipt', 'received_at', 'inspected_at', 'accepted_at', 'cancelled_at', 'cancelled_by', 'cancellation_reason', 'created_by', 'updated_by'])]
class ReceivingRecord extends Model
{
    protected $attributes = ['status' => 'draft'];

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function inspector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'inspected_by');
    }

    public function accepter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'accepted_by');
    }

    public function numberReservation(): BelongsTo
    {
        return $this->belongsTo(DocumentNumberReservation::class, 'document_number_reservation_id');
    }

    /** @return HasMany<ReceivingRecordLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(ReceivingRecordLine::class)->orderBy('line_number');
    }

    protected function casts(): array
    {
        return ['receiving_date' => 'date', 'status' => ReceivingStatus::class, 'received_at' => 'datetime', 'inspected_at' => 'datetime', 'accepted_at' => 'datetime', 'cancelled_at' => 'datetime'];
    }
}
