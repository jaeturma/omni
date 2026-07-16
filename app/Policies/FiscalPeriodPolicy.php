<?php

namespace App\Policies;

use App\Models\FiscalPeriod;
use App\Models\User;

class FiscalPeriodPolicy
{
    public function manage(User $user, FiscalPeriod $fiscalPeriod): bool
    {
        return true;
    }

    public function close(User $user, FiscalPeriod $fiscalPeriod): bool
    {
        return true;
    }

    public function lock(User $user, FiscalPeriod $fiscalPeriod): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return false;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, FiscalPeriod $fiscalPeriod): bool
    {
        return false;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return false;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, FiscalPeriod $fiscalPeriod): bool
    {
        return false;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, FiscalPeriod $fiscalPeriod): bool
    {
        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, FiscalPeriod $fiscalPeriod): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, FiscalPeriod $fiscalPeriod): bool
    {
        return false;
    }
}
