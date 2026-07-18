<?php

namespace App\Models;

use App\Enums\SupplierPaymentAllocationStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property SupplierPaymentAllocationStatus $status
 * @property numeric-string $amount
 */
#[Fillable(['supplier_payment_id', 'supplier_invoice_id', 'amount', 'status', 'allocated_at', 'allocated_by', 'reversed_at', 'reversed_by'])]
class SupplierPaymentAllocation extends Model
{
    protected $attributes = ['status' => 'active'];

    public function supplierPayment(): BelongsTo
    {
        return $this->belongsTo(SupplierPayment::class);
    }

    public function supplierInvoice(): BelongsTo
    {
        return $this->belongsTo(SupplierInvoice::class);
    }

    protected function casts(): array
    {
        return ['amount' => 'decimal:4', 'status' => SupplierPaymentAllocationStatus::class, 'allocated_at' => 'datetime', 'reversed_at' => 'datetime'];
    }
}
