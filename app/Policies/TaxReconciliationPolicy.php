<?php

namespace App\Policies;

use App\Models\TaxReconciliation;
use App\Models\User;

class TaxReconciliationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('tax-reconciliation.view');
    }

    public function view(User $user, TaxReconciliation $reconciliation): bool
    {
        return $user->can('tax-reconciliation.view');
    }

    public function adjust(User $user, TaxReconciliation $reconciliation): bool
    {
        return $user->can('tax-reconciliation.adjust');
    }

    public function review(User $user, TaxReconciliation $reconciliation): bool
    {
        return $user->can('tax-reconciliation.review');
    }

    public function export(User $user, TaxReconciliation $reconciliation): bool
    {
        return $user->can('tax-reconciliation.export');
    }

    public function delete(User $user, TaxReconciliation $reconciliation): bool
    {
        return false;
    }
}
