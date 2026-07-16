<?php

namespace App\Policies;

use App\Models\UnitOfMeasure;
use App\Models\User;

class UnitOfMeasurePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('units-of-measure.view');
    }

    public function view(User $user, UnitOfMeasure $unitOfMeasure): bool
    {
        return $user->can('units-of-measure.view');
    }

    public function create(User $user): bool
    {
        return $user->can('units-of-measure.create');
    }

    public function update(User $user, UnitOfMeasure $unitOfMeasure): bool
    {
        return $user->can('units-of-measure.update');
    }

    public function delete(User $user, UnitOfMeasure $unitOfMeasure): bool
    {
        return $user->can('units-of-measure.delete');
    }

    public function restore(User $user, UnitOfMeasure $unitOfMeasure): bool
    {
        return false;
    }

    public function forceDelete(User $user, UnitOfMeasure $unitOfMeasure): bool
    {
        return false;
    }
}
