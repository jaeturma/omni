<?php

namespace App\Policies;

use App\Models\ProductionCutover;
use App\Models\User;

class ProductionCutoverPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('production-cutover.view');
    }

    public function view(User $user, ProductionCutover $cutover): bool
    {
        return $user->can('production-cutover.view');
    }

    public function create(User $user): bool
    {
        return $user->can('production-cutover.manage');
    }

    public function approve(User $user, ProductionCutover $cutover): bool
    {
        return $user->can('production-cutover.approve') && $cutover->status === 'draft' && $cutover->created_by !== $user->id;
    }

    public function activate(User $user, ProductionCutover $cutover): bool
    {
        return $user->can('production-cutover.activate') && $cutover->status === 'approved';
    }

    public function delete(User $user, ProductionCutover $cutover): bool
    {
        return false;
    }
}
