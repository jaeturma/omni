<?php

namespace App\Models;

use App\Enums\SupplierPaymentStatus;
use App\Models\Concerns\HasPurchasingAttachments;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property SupplierPaymentStatus $status
 * @property Carbon $payment_date
 * @property numeric-string $gross_settlement_amount
 * @property numeric-string $withholding_amount
 * @property numeric-string $other_deductions
 * @property numeric-string $net_cash_paid
 * @property numeric-string $unapplied_amount
 */
#[Fillable(['supplier_id', 'payment_method_id', 'bank_id', 'document_number_reservation_id', 'payment_number', 'payment_date', 'reference_number', 'gross_settlement_amount', 'withholding_amount', 'other_deductions', 'net_cash_paid', 'unapplied_amount', 'notes', 'status', 'posted_at', 'posted_by', 'voided_at', 'voided_by', 'void_reason', 'created_by', 'updated_by'])]
class SupplierPayment extends Model
{
    use HasPurchasingAttachments;

    protected $attributes = ['withholding_amount' => 0, 'other_deductions' => 0, 'status' => 'draft'];

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class);
    }

    public function bank(): BelongsTo
    {
        return $this->belongsTo(Bank::class);
    }

    public function numberReservation(): BelongsTo
    {
        return $this->belongsTo(DocumentNumberReservation::class, 'document_number_reservation_id');
    }

    /** @return HasMany<SupplierPaymentAllocation, $this> */
    public function allocations(): HasMany
    {
        return $this->hasMany(SupplierPaymentAllocation::class);
    }

    protected function casts(): array
    {
        return ['payment_date' => 'date', 'gross_settlement_amount' => 'decimal:4', 'withholding_amount' => 'decimal:4', 'other_deductions' => 'decimal:4', 'net_cash_paid' => 'decimal:4', 'unapplied_amount' => 'decimal:4', 'status' => SupplierPaymentStatus::class, 'posted_at' => 'datetime', 'voided_at' => 'datetime'];
    }
}
