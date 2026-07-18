<?php

namespace App\Policies;

use App\Models\CanvassQuotation;
use App\Models\User;

class CanvassQuotationPolicy
{
    public function update(User $user, CanvassQuotation $quotation): bool
    {
        return $user->can('purchase-canvass.manage');
    }

    public function delete(User $user, CanvassQuotation $quotation): bool
    {
        return $user->can('purchase-canvass.manage');
    }
}
