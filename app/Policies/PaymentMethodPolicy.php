<?php

namespace App\Policies;

use App\Models\PaymentMethod;
use App\Models\User;

class PaymentMethodPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('payment-methods.view');
    }

    public function view(User $user, PaymentMethod $paymentMethod): bool
    {
        return $user->can('payment-methods.view');
    }

    public function create(User $user): bool
    {
        return $user->can('payment-methods.create');
    }

    public function update(User $user, PaymentMethod $paymentMethod): bool
    {
        return $user->can('payment-methods.update');
    }

    public function delete(User $user, PaymentMethod $paymentMethod): bool
    {
        return $user->can('payment-methods.delete');
    }

    public function restore(User $user, PaymentMethod $paymentMethod): bool
    {
        return false;
    }

    public function forceDelete(User $user, PaymentMethod $paymentMethod): bool
    {
        return false;
    }
}
