<?php

namespace App\Models;

use App\Enums\BankReconciliationStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property BankReconciliationStatus $status
 * @property Carbon $statement_start_date
 * @property Carbon $statement_end_date
 * @property numeric-string $statement_opening_balance
 * @property numeric-string $statement_closing_balance
 * @property numeric-string $system_opening_balance
 * @property numeric-string $system_closing_balance
 * @property numeric-string $unmatched_deposits
 * @property numeric-string $unmatched_withdrawals
 * @property numeric-string $bank_charges
 * @property numeric-string $interest_other_items
 * @property numeric-string $reconciliation_difference
 */
#[Fillable(['bank_statement_import_id', 'financial_account_id', 'statement_start_date', 'statement_end_date', 'statement_opening_balance', 'statement_closing_balance', 'system_opening_balance', 'system_closing_balance', 'unmatched_deposits', 'unmatched_withdrawals', 'bank_charges', 'interest_other_items', 'reconciliation_difference', 'status', 'exception_reason', 'created_by', 'reviewed_at', 'reviewed_by', 'finalized_at', 'finalized_by', 'reopened_at', 'reopened_by', 'reopen_reason'])]
class BankReconciliation extends Model
{
    protected $attributes = ['status' => 'draft', 'unmatched_deposits' => 0, 'unmatched_withdrawals' => 0, 'bank_charges' => 0, 'interest_other_items' => 0];

    /** @return BelongsTo<BankStatementImport, $this> */
    public function statementImport(): BelongsTo
    {
        return $this->belongsTo(BankStatementImport::class, 'bank_statement_import_id');
    }

    /** @return BelongsTo<FinancialAccount, $this> */
    public function financialAccount(): BelongsTo
    {
        return $this->belongsTo(FinancialAccount::class);
    }

    /** @return HasMany<BankReconciliationMatch, $this> */
    public function matches(): HasMany
    {
        return $this->hasMany(BankReconciliationMatch::class);
    }

    protected function casts(): array
    {
        return ['statement_start_date' => 'date', 'statement_end_date' => 'date', 'statement_opening_balance' => 'decimal:4',
            'statement_closing_balance' => 'decimal:4', 'system_opening_balance' => 'decimal:4', 'system_closing_balance' => 'decimal:4',
            'unmatched_deposits' => 'decimal:4', 'unmatched_withdrawals' => 'decimal:4', 'bank_charges' => 'decimal:4',
            'interest_other_items' => 'decimal:4', 'reconciliation_difference' => 'decimal:4', 'status' => BankReconciliationStatus::class,
            'reviewed_at' => 'datetime', 'finalized_at' => 'datetime', 'reopened_at' => 'datetime'];
    }
}
