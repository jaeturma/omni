<?php

namespace App\Models;

use App\Enums\DeliveryStatus;
use App\Models\Concerns\HasSalesAttachments;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** @property DeliveryStatus $status */
#[Fillable(['sales_order_id', 'customer_id', 'warehouse_id', 'document_number_reservation_id', 'delivery_number', 'delivery_date', 'customer_name', 'customer_po_number', 'inspection_reference', 'delivery_address', 'recipient_name', 'recipient_contact', 'notes', 'status', 'released_at', 'released_by', 'delivered_at', 'delivered_by', 'received_at', 'received_by_name', 'accepted_at', 'accepted_by', 'acceptance_notes', 'cancelled_at', 'cancelled_by', 'cancellation_reason', 'created_by', 'updated_by'])]
class Delivery extends Model
{
    use HasFactory, HasSalesAttachments;

    protected $attributes = ['status' => 'draft'];

    /** @return BelongsTo<SalesOrder,$this> */
    public function salesOrder(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class);
    }

    /** @return HasMany<DeliveryLine,$this> */
    public function lines(): HasMany
    {
        return $this->hasMany(DeliveryLine::class)->orderBy('line_number');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    /** @return HasMany<SalesInvoice, $this> */
    public function salesInvoices(): HasMany
    {
        return $this->hasMany(SalesInvoice::class);
    }

    protected function casts(): array
    {
        return ['delivery_date' => 'date', 'status' => DeliveryStatus::class, 'released_at' => 'datetime', 'delivered_at' => 'datetime', 'received_at' => 'datetime', 'accepted_at' => 'datetime', 'cancelled_at' => 'datetime'];
    }
}
