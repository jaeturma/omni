<?php

namespace App\Policies;

use App\Enums\BankReconciliationStatus;
use App\Models\BankReconciliation;
use App\Models\User;

class BankReconciliationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('bank-reconciliation.view');
    }

    public function view(User $user, BankReconciliation $reconciliation): bool
    {
        return $user->can('bank-reconciliation.view');
    }

    public function create(User $user): bool
    {
        return $user->can('bank-reconciliation.create');
    }

    public function match(User $user, BankReconciliation $reconciliation): bool
    {
        return $user->can('bank-reconciliation.match') && $reconciliation->status !== BankReconciliationStatus::Finalized;
    }

    public function finalize(User $user, BankReconciliation $reconciliation): bool
    {
        return $user->can('bank-reconciliation.finalize') && $reconciliation->status !== BankReconciliationStatus::Finalized;
    }

    public function reopen(User $user, BankReconciliation $reconciliation): bool
    {
        return $user->can('bank-reconciliation.reopen') && $reconciliation->status === BankReconciliationStatus::Finalized;
    }
}
