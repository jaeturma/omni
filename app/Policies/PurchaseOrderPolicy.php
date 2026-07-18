<?php

namespace App\Policies;

use App\Enums\PurchaseOrderStatus;
use App\Models\PurchaseOrder;
use App\Models\User;

class PurchaseOrderPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('purchase-orders.view');
    }

    public function view(User $user, PurchaseOrder $order): bool
    {
        return $user->can('purchase-orders.view');
    }

    public function create(User $user): bool
    {
        return $user->can('purchase-orders.create');
    }

    public function update(User $user, PurchaseOrder $order): bool
    {
        return $order->status === PurchaseOrderStatus::Draft && $user->can('purchase-orders.update');
    }

    public function delete(User $user, PurchaseOrder $order): bool
    {
        return $order->status === PurchaseOrderStatus::Draft && $user->can('purchase-orders.cancel');
    }

    public function approve(User $user, PurchaseOrder $order): bool
    {
        return $user->can('purchase-orders.approve');
    }

    public function send(User $user, PurchaseOrder $order): bool
    {
        return $user->can('purchase-orders.send');
    }

    public function cancel(User $user, PurchaseOrder $order): bool
    {
        return $user->can('purchase-orders.cancel');
    }

    public function print(User $user, PurchaseOrder $order): bool
    {
        return $user->can('purchase-orders.print');
    }
}
