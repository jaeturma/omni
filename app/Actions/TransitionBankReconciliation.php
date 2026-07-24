<?php

namespace App\Actions;

use App\Enums\BankReconciliationStatus;
use App\Enums\ReconciliationState;
use App\Models\BankReconciliation;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TransitionBankReconciliation
{
    public function handle(BankReconciliation $reconciliation, string $transition, ?string $reason, User $user): void
    {
        DB::transaction(function () use ($reconciliation, $transition, $reason, $user): void {
            $locked = BankReconciliation::query()->with('statementImport')->lockForUpdate()->findOrFail($reconciliation->id);
            if ($transition === 'review' && in_array($locked->status, [BankReconciliationStatus::Draft, BankReconciliationStatus::Reopened], true)) {
                $locked->update(['status' => BankReconciliationStatus::Reviewed, 'reviewed_at' => now(), 'reviewed_by' => $user->id]);

                return;
            }
            if ($transition === 'finalize' && $locked->status === BankReconciliationStatus::Reviewed) {
                if (bccomp($locked->reconciliation_difference, '0', 4) !== 0 && blank($reason)) {
                    throw ValidationException::withMessages(['exception_reason' => 'Documented authorization is required to finalize with a difference.']);
                }
                $locked->update(['status' => BankReconciliationStatus::Finalized, 'exception_reason' => $reason, 'finalized_at' => now(), 'finalized_by' => $user->id]);
                $locked->statementImport->lines()->where('match_status', ReconciliationState::Matched)->update(['match_status' => ReconciliationState::Reconciled]);
                $locked->statementImport->update(['finalized_at' => now(), 'finalized_by' => $user->id]);

                return;
            }
            if ($transition === 'reopen' && $locked->status === BankReconciliationStatus::Finalized && filled($reason)) {
                $locked->update(['status' => BankReconciliationStatus::Reopened, 'reopened_at' => now(), 'reopened_by' => $user->id, 'reopen_reason' => $reason]);
                $locked->statementImport->lines()->where('match_status', ReconciliationState::Reconciled)->update(['match_status' => ReconciliationState::Matched]);
                $locked->statementImport->update(['finalized_at' => null, 'finalized_by' => null]);

                return;
            }
            throw ValidationException::withMessages(['status' => 'The requested reconciliation transition is not allowed.']);
        });
    }
}
