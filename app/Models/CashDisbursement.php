<?php

namespace App\Models;

use App\Enums\CashDisbursementSourceType;
use App\Enums\CashDisbursementStatus;
use Database\Factories\CashDisbursementFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property CashDisbursementStatus $status
 * @property CashDisbursementSourceType $source_type
 * @property Carbon $disbursement_date
 * @property numeric-string $net_cash_out
 */
#[Fillable(['document_number_reservation_id', 'disbursement_number', 'disbursement_date', 'fiscal_period_id', 'financial_account_id', 'source_type', 'supplier_payment_id', 'expense_id', 'payee', 'payment_method_id', 'reference_number', 'gross_settlement', 'deductions_or_bank_charges', 'net_cash_out', 'notes', 'status', 'posted_at', 'posted_by', 'release_date', 'released_at', 'released_by', 'clearing_date', 'cleared_at', 'cleared_by', 'stopped_at', 'stopped_by', 'stop_reason', 'voided_at', 'voided_by', 'void_reason', 'created_by', 'updated_by'])]
class CashDisbursement extends Model
{
    /** @use HasFactory<CashDisbursementFactory> */
    use HasFactory;

    protected $attributes = ['deductions_or_bank_charges' => 0, 'status' => 'draft'];

    public function fiscalPeriod(): BelongsTo
    {
        return $this->belongsTo(FiscalPeriod::class);
    }

    public function financialAccount(): BelongsTo
    {
        return $this->belongsTo(FinancialAccount::class);
    }

    public function supplierPayment(): BelongsTo
    {
        return $this->belongsTo(SupplierPayment::class);
    }

    public function expense(): BelongsTo
    {
        return $this->belongsTo(Expense::class);
    }

    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class);
    }

    protected function casts(): array
    {
        return [
            'disbursement_date' => 'date', 'source_type' => CashDisbursementSourceType::class,
            'gross_settlement' => 'decimal:4', 'deductions_or_bank_charges' => 'decimal:4',
            'net_cash_out' => 'decimal:4', 'status' => CashDisbursementStatus::class,
            'posted_at' => 'datetime', 'release_date' => 'date', 'released_at' => 'datetime',
            'clearing_date' => 'date', 'cleared_at' => 'datetime', 'stopped_at' => 'datetime',
            'voided_at' => 'datetime',
        ];
    }
}
