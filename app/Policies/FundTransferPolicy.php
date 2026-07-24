<?php

namespace App\Policies;

use App\Models\FundTransfer;
use App\Models\User;

class FundTransferPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('fund-transfers.view');
    }

    public function view(User $user, FundTransfer $transfer): bool
    {
        return $user->can('fund-transfers.view');
    }

    public function create(User $user): bool
    {
        return $user->can('fund-transfers.create');
    }

    public function post(User $user, FundTransfer $transfer): bool
    {
        return $user->can('fund-transfers.post');
    }

    public function complete(User $user, FundTransfer $transfer): bool
    {
        return $user->can('fund-transfers.complete');
    }

    public function fail(User $user, FundTransfer $transfer): bool
    {
        return $user->can('fund-transfers.fail');
    }

    public function void(User $user, FundTransfer $transfer): bool
    {
        return $user->can('fund-transfers.void');
    }
}
