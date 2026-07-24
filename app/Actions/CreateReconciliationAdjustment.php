<?php

namespace App\Actions;

use App\Enums\BankReconciliationStatus;
use App\Enums\CashTransactionStatus;
use App\Enums\CashTransactionType;
use App\Enums\ReconciliationState;
use App\Models\BankReconciliation;
use App\Models\BankStatementLine;
use App\Models\CashTransaction;
use App\Models\DocumentSequence;
use App\Models\FinancialAccount;
use App\Models\User;
use App\Services\BankReconciliationCalculator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateReconciliationAdjustment
{
    public function __construct(private BankReconciliationCalculator $calculator, private IssueDocumentNumber $issueDocumentNumber) {}

    public function handle(BankReconciliation $reconciliation, BankStatementLine $line, string $kind, User $user): CashTransaction
    {
        return DB::transaction(function () use ($reconciliation, $line, $kind, $user): CashTransaction {
            $locked = BankReconciliation::query()->lockForUpdate()->findOrFail($reconciliation->id);
            if ($locked->status === BankReconciliationStatus::Finalized || $line->bank_statement_import_id !== $locked->bank_statement_import_id || $line->match_status !== ReconciliationState::Unreconciled) {
                throw ValidationException::withMessages(['adjustment' => 'Only an unmatched line on an open reconciliation can create an adjustment.']);
            }
            $account = FinancialAccount::query()->lockForUpdate()->findOrFail($locked->financial_account_id);
            $sequence = DocumentSequence::query()->where('document_type', 'cash_adjustment')->where('active', true)
                ->whereHas('fiscalYear', fn ($query) => $query->whereDate('starts_on', '<=', $line->transaction_date)->whereDate('ends_on', '>=', $line->transaction_date))->first();
            if (! $sequence) {
                throw ValidationException::withMessages(['adjustment' => 'An active cash adjustment document sequence is required.']);
            }
            $reservation = $this->issueDocumentNumber->handle($sequence, $user->id);
            $transaction = CashTransaction::query()->create([
                'bank_reconciliation_id' => $locked->id, 'financial_account_id' => $account->id,
                'document_number_reservation_id' => $reservation->id,
                'type' => CashTransactionType::Adjustment, 'adjustment_kind' => $kind, 'transaction_date' => $line->transaction_date,
                'amount' => $line->normalized_amount, 'reference_number' => $reservation->document_number,
                'status' => CashTransactionStatus::Posted, 'posted_at' => now(), 'posted_by' => $user->id, 'created_by' => $user->id,
            ]);
            $account->update(['current_balance' => bcadd($account->current_balance ?? $account->opening_balance, $line->normalized_amount, 4), 'updated_by' => $user->id]);
            $locked->matches()->create(['bank_statement_line_id' => $line->id, 'cash_transaction_id' => $transaction->id,
                'matched_amount' => $line->normalized_amount, 'confirmed_by' => $user->id, 'confirmed_at' => now()]);
            $line->update(['match_status' => ReconciliationState::Matched, 'matched_transaction_reference' => $transaction->reference_number]);
            $column = $kind === 'bank_charge' ? 'bank_charges' : 'interest_other_items';
            $value = $kind === 'bank_charge' ? bcmul($line->normalized_amount, '-1', 4) : $line->normalized_amount;
            $locked->update([$column => bcadd($locked->{$column}, $value, 4)]);
            $this->calculator->refresh($locked);

            return $transaction;
        });
    }
}
