<?php

namespace App\Models;

use App\Enums\PaymentAllocationStatus;
use Database\Factories\PaymentAllocationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property PaymentAllocationStatus $status
 * @property numeric-string $amount
 */
#[Fillable(['customer_payment_id', 'sales_invoice_id', 'amount', 'status', 'allocated_at', 'allocated_by', 'reversed_at', 'reversed_by'])]
class PaymentAllocation extends Model
{
    /** @use HasFactory<PaymentAllocationFactory> */
    use HasFactory;

    protected $attributes = ['status' => 'active'];

    public function customerPayment(): BelongsTo
    {
        return $this->belongsTo(CustomerPayment::class);
    }

    public function salesInvoice(): BelongsTo
    {
        return $this->belongsTo(SalesInvoice::class);
    }

    protected function casts(): array
    {
        return ['amount' => 'decimal:4', 'status' => PaymentAllocationStatus::class, 'allocated_at' => 'datetime', 'reversed_at' => 'datetime'];
    }
}
