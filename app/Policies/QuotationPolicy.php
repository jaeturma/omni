<?php

namespace App\Policies;

use App\Enums\QuotationStatus;
use App\Models\Quotation;
use App\Models\User;

class QuotationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('quotations.view');
    }

    public function view(User $user, Quotation $quotation): bool
    {
        return $user->can('quotations.view');
    }

    public function create(User $user): bool
    {
        return $user->can('quotations.create');
    }

    public function update(User $user, Quotation $quotation): bool
    {
        return $quotation->status === QuotationStatus::Draft && $user->can('quotations.update');
    }

    public function delete(User $user, Quotation $quotation): bool
    {
        return $quotation->status === QuotationStatus::Draft && $user->can('quotations.cancel');
    }

    public function approve(User $user, Quotation $quotation): bool
    {
        return $user->can('quotations.approve');
    }

    public function cancel(User $user, Quotation $quotation): bool
    {
        return $user->can('quotations.cancel');
    }

    public function print(User $user, Quotation $quotation): bool
    {
        return $user->can('quotations.print');
    }
}
