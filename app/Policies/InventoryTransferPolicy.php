<?php

namespace App\Policies;

use App\Models\InventoryTransfer;
use App\Models\User;

class InventoryTransferPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('inventory-transfers.view');
    }

    public function view(User $user, InventoryTransfer $transfer): bool
    {
        return $user->can('inventory-transfers.view');
    }

    public function create(User $user): bool
    {
        return $user->can('inventory-transfers.create');
    }

    public function approve(User $user, InventoryTransfer $transfer): bool
    {
        return $user->can('inventory-transfers.approve');
    }

    public function release(User $user, InventoryTransfer $transfer): bool
    {
        return $user->can('inventory-transfers.release');
    }

    public function receive(User $user, InventoryTransfer $transfer): bool
    {
        return $user->can('inventory-transfers.receive');
    }

    public function void(User $user, InventoryTransfer $transfer): bool
    {
        return $user->can('inventory-transfers.void');
    }
}
