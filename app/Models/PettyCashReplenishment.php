<?php

namespace App\Models;

use App\Enums\CashTransactionStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property Carbon $replenishment_date
 * @property numeric-string $amount
 */
#[Fillable(['petty_cash_fund_id', 'source_financial_account_id', 'replenishment_date', 'fiscal_period_id', 'amount', 'reference_number', 'status', 'posted_at', 'posted_by', 'created_by'])]
class PettyCashReplenishment extends Model
{
    protected $attributes = ['status' => 'posted'];

    public function fund(): BelongsTo
    {
        return $this->belongsTo(PettyCashFund::class, 'petty_cash_fund_id');
    }

    public function sourceFinancialAccount(): BelongsTo
    {
        return $this->belongsTo(FinancialAccount::class, 'source_financial_account_id');
    }

    public function fiscalPeriod(): BelongsTo
    {
        return $this->belongsTo(FiscalPeriod::class);
    }

    public function vouchers(): BelongsToMany
    {
        return $this->belongsToMany(PettyCashVoucher::class, 'petty_cash_replenishment_voucher')->withPivot('amount')->withTimestamps();
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(CashTransaction::class);
    }

    protected function casts(): array
    {
        return ['replenishment_date' => 'date', 'amount' => 'decimal:4', 'status' => CashTransactionStatus::class, 'posted_at' => 'datetime'];
    }
}
