<?php

namespace App\Policies;

use App\Models\Bank;
use App\Models\User;

class BankPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('banks.view');
    }

    public function view(User $user, Bank $bank): bool
    {
        return $user->can('banks.view');
    }

    public function create(User $user): bool
    {
        return $user->can('banks.create');
    }

    public function update(User $user, Bank $bank): bool
    {
        return $user->can('banks.update');
    }

    public function delete(User $user, Bank $bank): bool
    {
        return $user->can('banks.delete');
    }

    public function restore(User $user, Bank $bank): bool
    {
        return false;
    }

    public function forceDelete(User $user, Bank $bank): bool
    {
        return false;
    }
}
