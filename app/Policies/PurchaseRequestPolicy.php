<?php

namespace App\Policies;

use App\Enums\PurchaseRequestStatus;
use App\Models\PurchaseRequest;
use App\Models\User;

class PurchaseRequestPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('purchase-requests.view');
    }

    public function view(User $user, PurchaseRequest $request): bool
    {
        return $user->can('purchase-requests.view');
    }

    public function create(User $user): bool
    {
        return $user->can('purchase-requests.create');
    }

    public function update(User $user, PurchaseRequest $request): bool
    {
        return $request->status === PurchaseRequestStatus::Draft && $user->can('purchase-requests.update');
    }

    public function delete(User $user, PurchaseRequest $request): bool
    {
        return $request->status === PurchaseRequestStatus::Draft && $user->can('purchase-requests.cancel');
    }

    public function approve(User $user, PurchaseRequest $request): bool
    {
        return $user->can('purchase-requests.approve');
    }

    public function cancel(User $user, PurchaseRequest $request): bool
    {
        return $user->can('purchase-requests.cancel');
    }

    public function manageCanvass(User $user, PurchaseRequest $request): bool
    {
        return $user->can('purchase-canvass.manage');
    }
}
