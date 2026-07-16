<?php

namespace App\Policies;

use App\Enums\CustomerPaymentStatus;
use App\Models\CustomerPayment;
use App\Models\User;

class CustomerPaymentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('customer-payments.view');
    }

    public function view(User $user, CustomerPayment $payment): bool
    {
        return $user->can('customer-payments.view');
    }

    public function create(User $user): bool
    {
        return $user->can('customer-payments.create');
    }

    public function update(User $user, CustomerPayment $payment): bool
    {
        return $user->can('customer-payments.update') && $payment->status === CustomerPaymentStatus::Draft;
    }

    public function post(User $user, CustomerPayment $payment): bool
    {
        return $user->can('customer-payments.post');
    }

    public function allocate(User $user, CustomerPayment $payment): bool
    {
        return $user->can('customer-payments.allocate');
    }

    public function void(User $user, CustomerPayment $payment): bool
    {
        return $user->can('customer-payments.void');
    }
}
