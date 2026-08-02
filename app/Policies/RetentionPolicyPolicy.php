<?php

namespace App\Policies;

use App\Models\RetentionPolicy;
use App\Models\User;

class RetentionPolicyPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('privacy-settings.view');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, RetentionPolicy $retentionPolicy): bool
    {
        return $user->can('privacy-settings.view');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('privacy-settings.manage');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, RetentionPolicy $retentionPolicy): bool
    {
        return $user->can('privacy-settings.manage');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, RetentionPolicy $retentionPolicy): bool
    {
        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, RetentionPolicy $retentionPolicy): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, RetentionPolicy $retentionPolicy): bool
    {
        return false;
    }
}
