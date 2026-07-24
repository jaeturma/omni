<?php

namespace App\Policies;

use App\Models\PettyCashFund;
use App\Models\User;

class PettyCashFundPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('petty-cash.view');
    }

    public function view(User $user, PettyCashFund $fund): bool
    {
        return $user->can('petty-cash.view');
    }

    public function create(User $user): bool
    {
        return $user->can('petty-cash.manage-fund');
    }

    public function replenish(User $user, PettyCashFund $fund): bool
    {
        return $user->can('petty-cash.replenish');
    }

    public function update(User $user, PettyCashFund $fund): bool
    {
        return $user->can('petty-cash.manage-fund');
    }
}
