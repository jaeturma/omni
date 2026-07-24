<?php

namespace App\Models;

use App\Enums\CashTransactionStatus;
use App\Enums\CashTransactionType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property CashTransactionType $type
 * @property CashTransactionStatus $status
 * @property Carbon $transaction_date
 * @property numeric-string $amount
 * @property numeric-string $fee_amount
 * @property bool $reconciliation_match_exists
 */
#[Fillable(['fund_transfer_id', 'petty_cash_voucher_id', 'petty_cash_replenishment_id', 'bank_reconciliation_id', 'document_number_reservation_id', 'financial_account_id', 'type', 'adjustment_kind', 'transaction_date', 'amount', 'fee_amount', 'reference_number', 'status', 'posted_at', 'posted_by', 'voided_at', 'voided_by', 'void_reason', 'created_by'])]
class CashTransaction extends Model
{
    protected $attributes = ['fee_amount' => 0, 'status' => 'draft'];

    public function fundTransfer(): BelongsTo
    {
        return $this->belongsTo(FundTransfer::class);
    }

    public function financialAccount(): BelongsTo
    {
        return $this->belongsTo(FinancialAccount::class);
    }

    public function pettyCashVoucher(): BelongsTo
    {
        return $this->belongsTo(PettyCashVoucher::class);
    }

    public function pettyCashReplenishment(): BelongsTo
    {
        return $this->belongsTo(PettyCashReplenishment::class);
    }

    public function reconciliationMatch(): HasOne
    {
        return $this->hasOne(BankReconciliationMatch::class);
    }

    protected function casts(): array
    {
        return [
            'type' => CashTransactionType::class, 'transaction_date' => 'date',
            'amount' => 'decimal:4', 'fee_amount' => 'decimal:4',
            'status' => CashTransactionStatus::class, 'posted_at' => 'datetime', 'voided_at' => 'datetime',
        ];
    }
}
