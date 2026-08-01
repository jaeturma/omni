<?php

namespace App\Policies;

use App\Models\TaxObligation;
use App\Models\User;

class TaxObligationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('tax-calendar.view');
    }

    public function view(User $user, TaxObligation $taxObligation): bool
    {
        return $user->can('tax-calendar.view');
    }

    public function update(User $user, TaxObligation $taxObligation): bool
    {
        return $user->can('tax-calendar.update');
    }

    public function assignReviewer(User $user, TaxObligation $taxObligation): bool
    {
        return $user->can('tax-calendar.assign-reviewer');
    }

    public function delete(User $user, TaxObligation $taxObligation): bool
    {
        return false;
    }
}
