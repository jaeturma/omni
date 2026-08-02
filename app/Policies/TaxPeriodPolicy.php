<?php

namespace App\Policies;

use App\Models\TaxPeriod;
use App\Models\User;

class TaxPeriodPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('tax-dashboard.view');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, TaxPeriod $taxPeriod): bool
    {
        return $user->can('tax-dashboard.view');
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
    public function update(User $user, TaxPeriod $taxPeriod): bool
    {
        return false;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, TaxPeriod $taxPeriod): bool
    {
        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, TaxPeriod $taxPeriod): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, TaxPeriod $taxPeriod): bool
    {
        return false;
    }

    public function generate(User $user, TaxPeriod $taxPeriod): bool
    {
        return $user->can('tax-review-pack.generate');
    }

    public function download(User $user, TaxPeriod $taxPeriod): bool
    {
        return $user->can('tax-review-pack.download');
    }

    public function comment(User $user, TaxPeriod $taxPeriod): bool
    {
        return $user->can('tax-review-comments.manage');
    }
}
