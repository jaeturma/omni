<?php

namespace App\Models;

use App\Enums\FinancialAccountType;
use Database\Factories\FinancialAccountFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property numeric-string $opening_balance
 * @property Carbon|null $opening_balance_date
 * @property numeric-string|null $current_balance
 */
#[Fillable(['code', 'name', 'type', 'bank_id', 'branch_name', 'account_number', 'account_holder_name', 'currency', 'opening_balance', 'opening_balance_date', 'current_balance', 'active', 'allow_receipts', 'allow_disbursements', 'allow_transfers', 'allow_reconciliation', 'notes', 'opening_balance_set_at', 'opening_balance_set_by', 'activated_at', 'activated_by', 'deactivated_at', 'deactivated_by', 'deactivation_reason', 'created_by', 'updated_by'])]
class FinancialAccount extends Model
{
    /** @use HasFactory<FinancialAccountFactory> */
    use HasFactory;

    protected $attributes = ['currency' => 'PHP', 'opening_balance' => 0, 'active' => true, 'allow_receipts' => true, 'allow_disbursements' => true, 'allow_transfers' => true, 'allow_reconciliation' => false];

    protected $hidden = ['account_number'];

    public function bank(): BelongsTo
    {
        return $this->belongsTo(Bank::class);
    }

    public function maskedAccountNumber(): string
    {
        if (blank($this->account_number)) {
            return '—';
        }

        return '•••• '.str($this->account_number)->replaceMatches('/\s+/', '')->take(-4);
    }

    protected function casts(): array
    {
        return ['type' => FinancialAccountType::class, 'account_number' => 'encrypted', 'opening_balance' => 'decimal:4', 'current_balance' => 'decimal:4',
            'opening_balance_date' => 'date', 'active' => 'boolean', 'allow_receipts' => 'boolean', 'allow_disbursements' => 'boolean',
            'allow_transfers' => 'boolean', 'allow_reconciliation' => 'boolean', 'opening_balance_set_at' => 'datetime',
            'activated_at' => 'datetime', 'deactivated_at' => 'datetime'];
    }
}
