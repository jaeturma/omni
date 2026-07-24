<?php

namespace App\Policies;

use App\Models\PettyCashVoucher;
use App\Models\User;

class PettyCashVoucherPolicy
{
    public function view(User $user, PettyCashVoucher $voucher): bool
    {
        return $user->can('petty-cash.view');
    }

    public function create(User $user): bool
    {
        return $user->can('petty-cash.release');
    }

    public function release(User $user, PettyCashVoucher $voucher): bool
    {
        return $user->can('petty-cash.release');
    }

    public function liquidate(User $user, PettyCashVoucher $voucher): bool
    {
        return $user->can('petty-cash.liquidate');
    }

    public function markOverdue(User $user, PettyCashVoucher $voucher): bool
    {
        return $user->can('petty-cash.manage-fund');
    }

    public function void(User $user, PettyCashVoucher $voucher): bool
    {
        return $user->can('petty-cash.void');
    }
}
