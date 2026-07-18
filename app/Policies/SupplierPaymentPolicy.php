<?php

namespace App\Policies;

use App\Enums\SupplierPaymentStatus;
use App\Models\SupplierPayment;
use App\Models\User;

class SupplierPaymentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('supplier-payments.view');
    }

    public function view(User $user, SupplierPayment $payment): bool
    {
        return $user->can('supplier-payments.view');
    }

    public function create(User $user): bool
    {
        return $user->can('supplier-payments.create');
    }

    public function update(User $user, SupplierPayment $payment): bool
    {
        return $payment->status === SupplierPaymentStatus::Draft && $user->can('supplier-payments.update');
    }

    public function delete(User $user, SupplierPayment $payment): bool
    {
        return $payment->status === SupplierPaymentStatus::Draft && $user->can('supplier-payments.update');
    }

    public function post(User $user, SupplierPayment $payment): bool
    {
        return $payment->status === SupplierPaymentStatus::Draft && $user->can('supplier-payments.post');
    }

    public function allocate(User $user, SupplierPayment $payment): bool
    {
        return in_array($payment->status, [SupplierPaymentStatus::Posted, SupplierPaymentStatus::PartiallyAllocated], true) && $user->can('supplier-payments.allocate');
    }

    public function void(User $user, SupplierPayment $payment): bool
    {
        return in_array($payment->status, [SupplierPaymentStatus::Posted, SupplierPaymentStatus::PartiallyAllocated, SupplierPaymentStatus::FullyAllocated], true) && $user->can('supplier-payments.void');
    }

    public function print(User $user, SupplierPayment $payment): bool
    {
        return $user->can('supplier-payments.print');
    }
}
