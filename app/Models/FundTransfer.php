<?php

namespace App\Models;

use App\Enums\CashTransactionType;
use App\Enums\FundTransferStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property FundTransferStatus $status
 * @property Carbon $transfer_date
 * @property Carbon $destination_date
 * @property numeric-string $amount
 * @property numeric-string $transfer_fee
 */
#[Fillable(['document_number_reservation_id', 'transfer_number', 'transfer_date', 'destination_date', 'fiscal_period_id', 'source_financial_account_id', 'destination_financial_account_id', 'amount', 'transfer_fee', 'reference_number', 'notes', 'status', 'posted_at', 'posted_by', 'completed_at', 'completed_by', 'failed_at', 'failed_by', 'failure_reason', 'voided_at', 'voided_by', 'void_reason', 'created_by', 'updated_by'])]
class FundTransfer extends Model
{
    protected $attributes = ['transfer_fee' => 0, 'status' => 'draft'];

    public function fiscalPeriod(): BelongsTo
    {
        return $this->belongsTo(FiscalPeriod::class);
    }

    public function sourceFinancialAccount(): BelongsTo
    {
        return $this->belongsTo(FinancialAccount::class, 'source_financial_account_id');
    }

    public function destinationFinancialAccount(): BelongsTo
    {
        return $this->belongsTo(FinancialAccount::class, 'destination_financial_account_id');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(CashTransaction::class);
    }

    public function sourceTransaction(): HasOne
    {
        return $this->hasOne(CashTransaction::class)->where('type', CashTransactionType::TransferOut);
    }

    public function destinationTransaction(): HasOne
    {
        return $this->hasOne(CashTransaction::class)->where('type', CashTransactionType::TransferIn);
    }

    public function sourceCashOut(): string
    {
        return bcadd($this->amount, $this->transfer_fee, 4);
    }

    protected function casts(): array
    {
        return [
            'transfer_date' => 'date', 'destination_date' => 'date', 'amount' => 'decimal:4',
            'transfer_fee' => 'decimal:4', 'status' => FundTransferStatus::class,
            'posted_at' => 'datetime', 'completed_at' => 'datetime', 'failed_at' => 'datetime', 'voided_at' => 'datetime',
        ];
    }
}
