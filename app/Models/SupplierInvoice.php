<?php

namespace App\Models;

use App\Enums\SupplierInvoiceStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property SupplierInvoiceStatus $status
 * @property Carbon $invoice_date
 * @property Carbon $due_date
 * @property numeric-string $paid_amount
 * @property numeric-string $balance_due
 * @property numeric-string $total_payable
 */
#[Fillable(['supplier_id', 'purchase_order_id', 'receiving_record_id', 'fiscal_period_id', 'document_number_reservation_id', 'internal_number', 'supplier_invoice_number', 'invoice_date', 'due_date', 'supplier_name', 'supplier_tin', 'supplier_address', 'gross_purchase_amount', 'discount_amount', 'net_purchase_amount', 'freight_amount', 'other_charges_amount', 'withholding_expected_amount', 'total_payable', 'paid_amount', 'balance_due', 'notes', 'status', 'posted_at', 'posted_by', 'voided_at', 'voided_by', 'void_reason', 'created_by', 'updated_by'])]
class SupplierInvoice extends Model
{
    protected $attributes = ['gross_purchase_amount' => 0, 'discount_amount' => 0, 'net_purchase_amount' => 0, 'freight_amount' => 0, 'other_charges_amount' => 0, 'withholding_expected_amount' => 0, 'total_payable' => 0, 'paid_amount' => 0, 'balance_due' => 0, 'status' => 'draft'];

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function receivingRecord(): BelongsTo
    {
        return $this->belongsTo(ReceivingRecord::class);
    }

    public function fiscalPeriod(): BelongsTo
    {
        return $this->belongsTo(FiscalPeriod::class);
    }

    public function numberReservation(): BelongsTo
    {
        return $this->belongsTo(DocumentNumberReservation::class, 'document_number_reservation_id');
    }

    /** @return HasMany<SupplierInvoiceLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(SupplierInvoiceLine::class)->orderBy('line_number');
    }

    /** @return HasMany<SupplierPaymentAllocation, $this> */
    public function paymentAllocations(): HasMany
    {
        return $this->hasMany(SupplierPaymentAllocation::class);
    }

    protected function casts(): array
    {
        return ['invoice_date' => 'date', 'due_date' => 'date', 'gross_purchase_amount' => 'decimal:4', 'discount_amount' => 'decimal:4', 'net_purchase_amount' => 'decimal:4', 'freight_amount' => 'decimal:4', 'other_charges_amount' => 'decimal:4', 'withholding_expected_amount' => 'decimal:4', 'total_payable' => 'decimal:4', 'paid_amount' => 'decimal:4', 'balance_due' => 'decimal:4', 'status' => SupplierInvoiceStatus::class, 'posted_at' => 'datetime', 'voided_at' => 'datetime'];
    }
}
