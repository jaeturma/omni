<?php

namespace App\Models;

use App\Enums\CustomerPaymentStatus;
use Database\Factories\CustomerPaymentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property CustomerPaymentStatus $status
 * @property Carbon $payment_date
 * @property numeric-string $gross_settlement_amount
 * @property numeric-string $unapplied_amount
 */
#[Fillable(['customer_id', 'payment_method_id', 'bank_id', 'document_number_reservation_id', 'payment_number', 'payment_date', 'reference_number', 'gross_settlement_amount', 'withholding_amount', 'other_deductions', 'net_cash_received', 'unapplied_amount', 'notes', 'status', 'posted_at', 'posted_by', 'voided_at', 'voided_by', 'void_reason', 'created_by', 'updated_by'])]
class CustomerPayment extends Model
{
    /** @use HasFactory<CustomerPaymentFactory> */
    use HasFactory;

    protected $attributes = ['withholding_amount' => 0, 'other_deductions' => 0, 'status' => 'draft'];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class);
    }

    public function bank(): BelongsTo
    {
        return $this->belongsTo(Bank::class);
    }

    /** @return HasMany<PaymentAllocation, $this> */
    public function allocations(): HasMany
    {
        return $this->hasMany(PaymentAllocation::class);
    }

    protected function casts(): array
    {
        return ['payment_date' => 'date', 'gross_settlement_amount' => 'decimal:4', 'withholding_amount' => 'decimal:4', 'other_deductions' => 'decimal:4', 'net_cash_received' => 'decimal:4', 'unapplied_amount' => 'decimal:4', 'status' => CustomerPaymentStatus::class, 'posted_at' => 'datetime', 'voided_at' => 'datetime'];
    }
}
