<?php

namespace App\Models;

use App\Models\Concerns\HasPurchasingAttachments;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['purchase_request_id', 'supplier_id', 'supplier_name', 'supplier_tin', 'supplier_address', 'quoted_amount', 'quotation_date', 'validity_date', 'delivery_terms', 'payment_terms', 'selected', 'evaluation_notes', 'created_by', 'updated_by'])]
class CanvassQuotation extends Model
{
    use HasPurchasingAttachments;

    protected $attributes = ['selected' => false];

    public function purchaseRequest(): BelongsTo
    {
        return $this->belongsTo(PurchaseRequest::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    protected function casts(): array
    {
        return ['quoted_amount' => 'decimal:4', 'quotation_date' => 'date', 'validity_date' => 'date', 'selected' => 'boolean'];
    }
}
