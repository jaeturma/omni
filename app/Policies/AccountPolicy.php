<?php

namespace App\Policies;

use App\Models\Account;
use App\Models\User;

class AccountPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('chart-of-accounts.view');
    }

    public function view(User $user, Account $account): bool
    {
        return $user->can('chart-of-accounts.view');
    }

    public function create(User $user): bool
    {
        return $user->can('chart-of-accounts.create');
    }

    public function update(User $user, Account $account): bool
    {
        return $user->can('chart-of-accounts.update');
    }

    public function activate(User $user, Account $account): bool
    {
        return $user->can('chart-of-accounts.activate');
    }

    public function deactivate(User $user, Account $account): bool
    {
        return $user->can('chart-of-accounts.deactivate');
    }
}
