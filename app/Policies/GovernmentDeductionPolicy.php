<?php

namespace App\Policies;

use App\Enums\GovernmentDeductionStatus;
use App\Models\GovernmentDeduction;
use App\Models\User;

class GovernmentDeductionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('government-deductions.view');
    }

    public function view(User $user, GovernmentDeduction $deduction): bool
    {
        return $user->can('government-deductions.view');
    }

    public function create(User $user): bool
    {
        return $user->can('government-deductions.create');
    }

    public function update(User $user, GovernmentDeduction $deduction): bool
    {
        return $user->can('government-deductions.update') && in_array($deduction->status, [GovernmentDeductionStatus::Pending, GovernmentDeductionStatus::Received], true);
    }

    public function verify(User $user, GovernmentDeduction $deduction): bool
    {
        return $user->can('government-deductions.verify');
    }

    public function void(User $user, GovernmentDeduction $deduction): bool
    {
        return $user->can('government-deductions.void');
    }
}
