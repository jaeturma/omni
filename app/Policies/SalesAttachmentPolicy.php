<?php

namespace App\Policies;

use App\Models\SalesAttachment;
use App\Models\User;
use App\Services\SalesAttachmentManager;

class SalesAttachmentPolicy
{
    public function __construct(private readonly SalesAttachmentManager $manager) {}

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('sales-attachments.view');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, SalesAttachment $salesAttachment): bool
    {
        return $user->can('sales-attachments.view');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('sales-attachments.upload');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, SalesAttachment $salesAttachment): bool
    {
        return false;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, SalesAttachment $salesAttachment): bool
    {
        return $user->can('sales-attachments.delete') && ! $this->manager->isProtected($salesAttachment->attachable);
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, SalesAttachment $salesAttachment): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, SalesAttachment $salesAttachment): bool
    {
        return false;
    }
}
