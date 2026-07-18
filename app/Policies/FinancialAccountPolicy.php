<?php

namespace App\Policies;

use App\Models\FinancialAccount;
use App\Models\User;

class FinancialAccountPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('financial-accounts.view');
    }

    public function view(User $user, FinancialAccount $account): bool
    {
        return $user->can('financial-accounts.view');
    }

    public function create(User $user): bool
    {
        return $user->can('financial-accounts.create');
    }

    public function update(User $user, FinancialAccount $account): bool
    {
        return $user->can('financial-accounts.update') && $account->active;
    }

    public function activate(User $user, FinancialAccount $account): bool
    {
        return $user->can('financial-accounts.activate') && ! $account->active;
    }

    public function deactivate(User $user, FinancialAccount $account): bool
    {
        return $user->can('financial-accounts.deactivate') && $account->active;
    }

    public function viewSensitive(User $user, FinancialAccount $account): bool
    {
        return $user->can('financial-accounts.view-sensitive');
    }

    public function delete(User $user, FinancialAccount $account): bool
    {
        return false;
    }
}
