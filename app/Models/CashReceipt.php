<?php

namespace App\Models;

use App\Enums\CashReceiptSourceType;
use App\Enums\CashReceiptStatus;
use Database\Factories\CashReceiptFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property CashReceiptStatus $status
 * @property CashReceiptSourceType $source_type
 * @property Carbon $receipt_date
 * @property numeric-string $net_amount_deposited
 */
#[Fillable(['document_number_reservation_id', 'receipt_number', 'receipt_date', 'fiscal_period_id', 'financial_account_id', 'source_type', 'customer_id', 'customer_payment_id', 'payer_name', 'payment_method_id', 'reference_number', 'gross_receipt', 'deductions_or_fees', 'net_amount_deposited', 'notes', 'status', 'posted_at', 'posted_by', 'clearing_date', 'cleared_at', 'cleared_by', 'bounced_at', 'bounced_by', 'bounce_reason', 'voided_at', 'voided_by', 'void_reason', 'created_by', 'updated_by'])]

class CashReceipt extends Model
{
    /** @use HasFactory<CashReceiptFactory> */
    use HasFactory;

    protected $attributes = ['deductions_or_fees' => 0, 'status' => 'draft'];

    public function fiscalPeriod(): BelongsTo
    {
        return $this->belongsTo(FiscalPeriod::class);
    }

    public function financialAccount(): BelongsTo
    {
        return $this->belongsTo(FinancialAccount::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function customerPayment(): BelongsTo
    {
        return $this->belongsTo(CustomerPayment::class);
    }

    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class);
    }

    protected function casts(): array
    {
        return ['receipt_date' => 'date', 'source_type' => CashReceiptSourceType::class, 'gross_receipt' => 'decimal:4',
            'deductions_or_fees' => 'decimal:4', 'net_amount_deposited' => 'decimal:4', 'status' => CashReceiptStatus::class,
            'posted_at' => 'datetime', 'clearing_date' => 'date', 'cleared_at' => 'datetime', 'bounced_at' => 'datetime', 'voided_at' => 'datetime'];
    }
}
