<?php

namespace App\Services;

use App\Enums\CashTransactionStatus;
use App\Enums\CashTransactionType;
use App\Enums\ReconciliationState;
use App\Models\BankReconciliation;
use App\Models\BankStatementImport;
use App\Models\CashTransaction;

class BankReconciliationCalculator
{
    private const INFLOWS = [CashTransactionType::CustomerReceipt, CashTransactionType::Deposit, CashTransactionType::TransferIn, CashTransactionType::PettyCashReturn, CashTransactionType::OpeningBalance];

    public function balances(BankStatementImport $import): array
    {
        $account = $import->financialAccount;
        $opening = $account->opening_balance;
        foreach (CashTransaction::query()->whereBelongsTo($account, 'financialAccount')->where('status', CashTransactionStatus::Posted)
            ->whereDate('transaction_date', '<', $import->statement_start_date)->cursor() as $transaction) {
            $opening = bcadd($opening, $this->signed($transaction), 4);
        }
        $closing = $opening;
        foreach (CashTransaction::query()->whereBelongsTo($account, 'financialAccount')->where('status', CashTransactionStatus::Posted)
            ->whereBetween('transaction_date', [$import->statement_start_date, $import->statement_end_date])->cursor() as $transaction) {
            $closing = bcadd($closing, $this->signed($transaction), 4);
        }

        return [$opening, $closing];
    }

    public function refresh(BankReconciliation $reconciliation): void
    {
        [, $systemClosing] = $this->balances($reconciliation->statementImport()->with('financialAccount')->firstOrFail());
        $unmatched = $reconciliation->statementImport->lines()->whereIn('match_status', [ReconciliationState::Unreconciled, ReconciliationState::Disputed])->get(['normalized_amount']);
        $deposits = '0.0000';
        $withdrawals = '0.0000';
        foreach ($unmatched as $line) {
            if (bccomp($line->normalized_amount, '0', 4) >= 0) {
                $deposits = bcadd($deposits, $line->normalized_amount, 4);
            } else {
                $withdrawals = bcadd($withdrawals, bcmul($line->normalized_amount, '-1', 4), 4);
            }
        }
        $adjustedSystem = bcsub(bcadd($systemClosing, $deposits, 4), $withdrawals, 4);
        $reconciliation->update(['system_closing_balance' => $systemClosing, 'unmatched_deposits' => $deposits,
            'unmatched_withdrawals' => $withdrawals, 'reconciliation_difference' => bcsub($reconciliation->statement_closing_balance, $adjustedSystem, 4)]);
    }

    public function signed(CashTransaction $transaction): string
    {
        if ($transaction->type === CashTransactionType::Adjustment) {
            return $transaction->amount;
        }

        return in_array($transaction->type, self::INFLOWS, true)
            ? bcsub($transaction->amount, $transaction->fee_amount, 4)
            : bcmul(bcadd($transaction->amount, $transaction->fee_amount, 4), '-1', 4);
    }
}
