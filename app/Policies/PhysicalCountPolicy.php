<?php

namespace App\Policies;

use App\Models\PhysicalCount;
use App\Models\User;

class PhysicalCountPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('physical-counts.view');
    }

    public function view(User $user, PhysicalCount $physicalCount): bool
    {
        return $user->can('physical-counts.view');
    }

    public function create(User $user): bool
    {
        return $user->can('physical-counts.create');
    }

    public function count(User $user, PhysicalCount $physicalCount): bool
    {
        return $user->can('physical-counts.count');
    }

    public function review(User $user, PhysicalCount $physicalCount): bool
    {
        return $user->can('physical-counts.review');
    }

    public function approve(User $user, PhysicalCount $physicalCount): bool
    {
        return $user->can('physical-counts.approve');
    }

    public function post(User $user, PhysicalCount $physicalCount): bool
    {
        return $user->can('physical-counts.post');
    }

    public function void(User $user, PhysicalCount $physicalCount): bool
    {
        return $user->can('physical-counts.void');
    }
}
