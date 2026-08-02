<?php

namespace App\Policies;

use App\Models\Supplier;
use App\Models\User;
use App\Services\RecordProtection;

class SupplierPolicy
{
    public function __construct(private readonly RecordProtection $protection) {}

    public function viewAny(User $user): bool
    {
        return $user->can('suppliers.view');
    }

    public function view(User $user, Supplier $supplier): bool
    {
        return $user->can('suppliers.view');
    }

    public function create(User $user): bool
    {
        return $user->can('suppliers.create');
    }

    public function update(User $user, Supplier $supplier): bool
    {
        return $user->can('suppliers.update');
    }

    public function delete(User $user, Supplier $supplier): bool
    {
        return $user->can('suppliers.delete') && ! $this->protection->supplierHasHistory($supplier);
    }

    public function restore(User $user, Supplier $supplier): bool
    {
        return false;
    }

    public function forceDelete(User $user, Supplier $supplier): bool
    {
        return false;
    }
}
