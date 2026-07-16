<?php

namespace App\Policies;

use App\Models\BusinessProfile;
use App\Models\User;

class BusinessProfilePolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('business-profile.view');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, BusinessProfile $businessProfile): bool
    {
        return $user->can('business-profile.view');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('business-profile.update');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, BusinessProfile $businessProfile): bool
    {
        return $user->can('business-profile.update');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, BusinessProfile $businessProfile): bool
    {
        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, BusinessProfile $businessProfile): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, BusinessProfile $businessProfile): bool
    {
        return false;
    }
}
