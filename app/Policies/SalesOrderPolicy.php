<?php

namespace App\Policies;

use App\Enums\SalesOrderStatus;
use App\Models\SalesOrder;
use App\Models\User;

class SalesOrderPolicy
{
    public function viewAny(User $u): bool
    {
        return $u->can('sales-orders.view');
    }

    public function view(User $u, SalesOrder $o): bool
    {
        return $u->can('sales-orders.view');
    }

    public function create(User $u): bool
    {
        return $u->can('sales-orders.create');
    }

    public function update(User $u, SalesOrder $o): bool
    {
        return $o->status === SalesOrderStatus::Draft && $u->can('sales-orders.update');
    }

    public function delete(User $u, SalesOrder $o): bool
    {
        return $o->status === SalesOrderStatus::Draft && $u->can('sales-orders.cancel');
    }

    public function confirm(User $u, SalesOrder $o): bool
    {
        return $u->can('sales-orders.confirm');
    }

    public function cancel(User $u, SalesOrder $o): bool
    {
        return $u->can('sales-orders.cancel');
    }
}
