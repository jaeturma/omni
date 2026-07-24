<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property numeric-string $approved_fund_limit
 * @property numeric-string $current_operational_balance
 */
#[Fillable(['financial_account_id', 'custodian_id', 'approved_fund_limit', 'current_operational_balance', 'active', 'created_by', 'updated_by'])]
class PettyCashFund extends Model
{
    protected $attributes = ['active' => true];

    public function financialAccount(): BelongsTo
    {
        return $this->belongsTo(FinancialAccount::class);
    }

    public function custodian(): BelongsTo
    {
        return $this->belongsTo(User::class, 'custodian_id');
    }

    public function vouchers(): HasMany
    {
        return $this->hasMany(PettyCashVoucher::class);
    }

    public function replenishments(): HasMany
    {
        return $this->hasMany(PettyCashReplenishment::class);
    }

    protected function casts(): array
    {
        return [
            'approved_fund_limit' => 'decimal:4', 'current_operational_balance' => 'decimal:4', 'active' => 'boolean',
        ];
    }
}
