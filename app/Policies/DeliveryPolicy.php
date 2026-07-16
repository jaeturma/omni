<?php

namespace App\Policies;

use App\Models\Delivery;
use App\Models\User;

class DeliveryPolicy
{
    public function viewAny(User $u): bool
    {
        return $u->can('deliveries.view');
    }

    public function view(User $u, Delivery $d): bool
    {
        return $u->can('deliveries.view');
    }

    public function create(User $u): bool
    {
        return $u->can('deliveries.create');
    }

    public function release(User $u, Delivery $d): bool
    {
        return $u->can('deliveries.release');
    }

    public function accept(User $u, Delivery $d): bool
    {
        return $u->can('deliveries.accept');
    }

    public function cancel(User $u, Delivery $d): bool
    {
        return $u->can('deliveries.cancel');
    }

    public function print(User $u, Delivery $d): bool
    {
        return $u->can('deliveries.print');
    }
}
