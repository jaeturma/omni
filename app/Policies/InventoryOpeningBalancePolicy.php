<?php

namespace App\Policies;

use App\Models\InventoryOpeningBalance;
use App\Models\User;

class InventoryOpeningBalancePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('inventory-opening.view');
    }

    public function view(User $user, InventoryOpeningBalance $opening): bool
    {
        return $user->can('inventory-opening.view');
    }

    public function create(User $user): bool
    {
        return $user->can('inventory-opening.create');
    }

    public function post(User $user, InventoryOpeningBalance $opening): bool
    {
        return $user->can('inventory-opening.post');
    }

    public function void(User $user, InventoryOpeningBalance $opening): bool
    {
        return $user->can('inventory-opening.void');
    }
}
