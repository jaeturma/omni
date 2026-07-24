<?php

namespace App\Policies;

use App\Enums\CashDisbursementStatus;
use App\Models\CashDisbursement;
use App\Models\User;

class CashDisbursementPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('cash-disbursements.view');
    }

    public function view(User $user, CashDisbursement $disbursement): bool
    {
        return $user->can('cash-disbursements.view');
    }

    public function create(User $user): bool
    {
        return $user->can('cash-disbursements.create');
    }

    public function update(User $user, CashDisbursement $disbursement): bool
    {
        return $user->can('cash-disbursements.update') && $disbursement->status === CashDisbursementStatus::Draft;
    }

    public function delete(User $user, CashDisbursement $disbursement): bool
    {
        return false;
    }

    public function post(User $user, CashDisbursement $disbursement): bool
    {
        return $user->can('cash-disbursements.post');
    }

    public function release(User $user, CashDisbursement $disbursement): bool
    {
        return $user->can('cash-disbursements.release');
    }

    public function clear(User $user, CashDisbursement $disbursement): bool
    {
        return $user->can('cash-disbursements.clear');
    }

    public function stop(User $user, CashDisbursement $disbursement): bool
    {
        return $user->can('cash-disbursements.stop');
    }

    public function void(User $user, CashDisbursement $disbursement): bool
    {
        return $user->can('cash-disbursements.void');
    }

    public function print(User $user, CashDisbursement $disbursement): bool
    {
        return $user->can('cash-disbursements.print');
    }
}
