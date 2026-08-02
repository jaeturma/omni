<?php

namespace App\Policies;

use App\Models\TaxFiling;
use App\Models\User;

class TaxFilingPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('tax-filings.view');
    }

    public function view(User $user, TaxFiling $filing): bool
    {
        return $user->can('tax-filings.view');
    }

    public function update(User $user, TaxFiling $filing): bool
    {
        return false;
    }

    public function delete(User $user, TaxFiling $filing): bool
    {
        return false;
    }
}
