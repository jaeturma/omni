<?php

namespace App\Models;

use App\Enums\PettyCashVoucherStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property PettyCashVoucherStatus $status
 * @property Carbon $voucher_date
 * @property numeric-string $amount_released
 * @property numeric-string $amount_liquidated
 * @property numeric-string $amount_returned
 */
#[Fillable(['petty_cash_fund_id', 'document_number_reservation_id', 'voucher_number', 'voucher_date', 'fiscal_period_id', 'payee', 'expense_category', 'purpose', 'amount_released', 'amount_liquidated', 'amount_returned', 'receipt_available', 'expense_id', 'status', 'released_at', 'released_by', 'liquidated_at', 'liquidated_by', 'overdue_at', 'overdue_by', 'voided_at', 'voided_by', 'void_reason', 'created_by', 'updated_by'])]
class PettyCashVoucher extends Model
{
    protected $attributes = ['amount_liquidated' => 0, 'amount_returned' => 0, 'receipt_available' => false, 'status' => 'draft'];

    public function fund(): BelongsTo
    {
        return $this->belongsTo(PettyCashFund::class, 'petty_cash_fund_id');
    }

    public function fiscalPeriod(): BelongsTo
    {
        return $this->belongsTo(FiscalPeriod::class);
    }

    public function expense(): BelongsTo
    {
        return $this->belongsTo(Expense::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(CashTransaction::class);
    }

    public function replenishments(): BelongsToMany
    {
        return $this->belongsToMany(PettyCashReplenishment::class, 'petty_cash_replenishment_voucher')->withPivot('amount')->withTimestamps();
    }

    protected function casts(): array
    {
        return [
            'voucher_date' => 'date', 'amount_released' => 'decimal:4', 'amount_liquidated' => 'decimal:4',
            'amount_returned' => 'decimal:4', 'receipt_available' => 'boolean', 'status' => PettyCashVoucherStatus::class,
            'released_at' => 'datetime', 'liquidated_at' => 'datetime', 'overdue_at' => 'datetime', 'voided_at' => 'datetime',
        ];
    }
}
