<?php

namespace App\Policies;

use App\Models\InventoryAdjustment;
use App\Models\User;

class InventoryAdjustmentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('inventory-adjustments.view');
    }

    public function view(User $user, InventoryAdjustment $adjustment): bool
    {
        return $user->can('inventory-adjustments.view');
    }

    public function create(User $user): bool
    {
        return $user->can('inventory-adjustments.create');
    }

    public function approve(User $user, InventoryAdjustment $adjustment): bool
    {
        return $user->can('inventory-adjustments.approve');
    }

    public function post(User $user, InventoryAdjustment $adjustment): bool
    {
        return $user->can('inventory-adjustments.post');
    }

    public function void(User $user, InventoryAdjustment $adjustment): bool
    {
        return $user->can('inventory-adjustments.void');
    }
}
