<?php

namespace App\Models;

use App\Enums\SalesInvoiceStatus;
use App\Models\Concerns\HasSalesAttachments;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property SalesInvoiceStatus $status
 * @property numeric-string $paid_amount
 * @property numeric-string $balance_due
 * @property numeric-string $gross_amount
 * @property Carbon $invoice_date
 * @property Carbon $due_date
 */
#[Fillable(['sales_order_id', 'delivery_id', 'customer_id', 'fiscal_period_id', 'document_number_reservation_id', 'invoice_number', 'invoice_date', 'due_date', 'customer_name', 'customer_tin', 'billing_address', 'customer_po_number', 'source_type', 'gross_amount', 'discount_amount', 'net_sales_amount', 'expected_withholding_amount', 'total_receivable', 'paid_amount', 'balance_due', 'notes', 'status', 'posted_at', 'posted_by', 'voided_at', 'voided_by', 'void_reason', 'created_by', 'updated_by'])]
class SalesInvoice extends Model
{
    use HasFactory, HasSalesAttachments;

    protected $attributes = ['gross_amount' => 0, 'discount_amount' => 0, 'net_sales_amount' => 0, 'expected_withholding_amount' => 0, 'total_receivable' => 0, 'paid_amount' => 0, 'balance_due' => 0, 'status' => 'draft'];

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /** @return BelongsTo<FiscalPeriod, $this> */
    public function fiscalPeriod(): BelongsTo
    {
        return $this->belongsTo(FiscalPeriod::class);
    }

    public function salesOrder(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class);
    }

    public function delivery(): BelongsTo
    {
        return $this->belongsTo(Delivery::class);
    }

    /** @return HasMany<SalesInvoiceLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(SalesInvoiceLine::class)->orderBy('line_number');
    }

    /** @return HasMany<PaymentAllocation, $this> */
    public function paymentAllocations(): HasMany
    {
        return $this->hasMany(PaymentAllocation::class);
    }

    /** @return HasMany<GovernmentDeduction, $this> */
    public function governmentDeductions(): HasMany
    {
        return $this->hasMany(GovernmentDeduction::class);
    }

    protected function casts(): array
    {
        return ['invoice_date' => 'date', 'due_date' => 'date', 'gross_amount' => 'decimal:4', 'discount_amount' => 'decimal:4', 'net_sales_amount' => 'decimal:4', 'expected_withholding_amount' => 'decimal:4', 'total_receivable' => 'decimal:4', 'paid_amount' => 'decimal:4', 'balance_due' => 'decimal:4', 'status' => SalesInvoiceStatus::class, 'posted_at' => 'datetime', 'voided_at' => 'datetime'];
    }
}
