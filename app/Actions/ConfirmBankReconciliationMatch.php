<?php

namespace App\Actions;

use App\Enums\BankReconciliationStatus;
use App\Enums\CashTransactionStatus;
use App\Enums\ReconciliationState;
use App\Models\BankReconciliation;
use App\Models\BankReconciliationMatch;
use App\Models\BankStatementLine;
use App\Models\CashTransaction;
use App\Models\User;
use App\Services\BankReconciliationCalculator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ConfirmBankReconciliationMatch
{
    public function __construct(private BankReconciliationCalculator $calculator) {}

    public function handle(BankReconciliation $reconciliation, BankStatementLine $line, array $transactionIds, User $user): void
    {
        DB::transaction(function () use ($reconciliation, $line, $transactionIds, $user): void {
            $locked = BankReconciliation::query()->lockForUpdate()->findOrFail($reconciliation->id);
            if (! in_array($locked->status, [BankReconciliationStatus::Draft, BankReconciliationStatus::Reviewed, BankReconciliationStatus::Reopened], true)
                || $line->bank_statement_import_id !== $locked->bank_statement_import_id || $line->match_status === ReconciliationState::Reconciled) {
                throw ValidationException::withMessages(['match' => 'This reconciliation line is locked or invalid.']);
            }
            $transactions = CashTransaction::query()->whereIn('id', $transactionIds)->where('financial_account_id', $locked->financial_account_id)
                ->where('status', CashTransactionStatus::Posted)->whereBetween('transaction_date', [$locked->statement_start_date->copy()->subDays(3), $locked->statement_end_date->copy()->addDays(3)])->get();
            if ($transactions->count() !== count(array_unique($transactionIds)) || BankReconciliationMatch::query()->whereIn('cash_transaction_id', $transactionIds)->exists()) {
                throw ValidationException::withMessages(['cash_transaction_ids' => 'Every selected transaction must be posted, available, and belong to this account.']);
            }
            $total = '0.0000';
            foreach ($transactions as $transaction) {
                $total = bcadd($total, $this->calculator->signed($transaction), 4);
            }
            if (bccomp($total, $line->normalized_amount, 4) !== 0) {
                throw ValidationException::withMessages(['cash_transaction_ids' => 'Selected transactions must total the statement line amount.']);
            }
            foreach ($transactions as $transaction) {
                $locked->matches()->create(['bank_statement_line_id' => $line->id, 'cash_transaction_id' => $transaction->id,
                    'matched_amount' => $this->calculator->signed($transaction), 'confirmed_by' => $user->id, 'confirmed_at' => now()]);
            }
            $line->update(['match_status' => ReconciliationState::Matched, 'matched_transaction_reference' => $transactions->pluck('reference_number')->filter()->join(', ') ?: 'Confirmed system transaction(s)']);
            $this->calculator->refresh($locked);
        });
    }
}
