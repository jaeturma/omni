<?php

namespace App\Actions;

use App\Enums\BankReconciliationStatus;
use App\Enums\CashTransactionStatus;
use App\Enums\FundTransferStatus;
use App\Enums\ReconciliationState;
use App\Models\BankReconciliation;
use App\Models\BankStatementLine;
use App\Models\CashTransaction;
use App\Models\FinancialAccount;
use App\Models\FundTransfer;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ChangeFinancialAccountStatus
{
    public function handle(FinancialAccount $account, bool $activate, ?string $reason, User $user): void
    {
        DB::transaction(function () use ($account, $activate, $reason, $user): void {
            $locked = FinancialAccount::query()->lockForUpdate()->findOrFail($account->id);
            if (! $activate) {
                $this->ensureDeactivationIsSafe($locked);
            }
            $locked->update($activate
                ? ['active' => true, 'activated_at' => now(), 'activated_by' => $user->id, 'deactivated_at' => null, 'deactivated_by' => null, 'deactivation_reason' => null, 'updated_by' => $user->id]
                : ['active' => false, 'deactivated_at' => now(), 'deactivated_by' => $user->id, 'deactivation_reason' => $reason, 'updated_by' => $user->id]);
        });
    }

    private function ensureDeactivationIsSafe(FinancialAccount $account): void
    {
        $transferAttention = FundTransfer::query()->where('status', FundTransferStatus::Posted)
            ->where(fn ($query) => $query->where('source_financial_account_id', $account->id)->orWhere('destination_financial_account_id', $account->id))->exists();
        if ($transferAttention || $account->pettyCashFund()->where('active', true)->exists()) {
            throw ValidationException::withMessages(['active' => 'Resolve active petty-cash or in-transit transfer activity before deactivating this account.']);
        }
        if (! $account->allow_reconciliation) {
            return;
        }
        $postedAttention = CashTransaction::query()->whereBelongsTo($account, 'financialAccount')->where('status', CashTransactionStatus::Posted)
            ->whereDoesntHave('finalizedReconciliationMatch')->exists();
        $statementAttention = BankStatementLine::query()->whereIn('match_status', [ReconciliationState::Unreconciled, ReconciliationState::Matched, ReconciliationState::Disputed])
            ->whereHas('bankStatementImport', fn ($query) => $query->where('financial_account_id', $account->id))->exists();
        $reconciliationAttention = BankReconciliation::query()->whereBelongsTo($account, 'financialAccount')
            ->where('status', '!=', BankReconciliationStatus::Finalized)->exists();
        if ($postedAttention || $statementAttention || $reconciliationAttention) {
            throw ValidationException::withMessages(['active' => 'Resolve unreconciled cash and statement activity before deactivating this account.']);
        }
    }
}
