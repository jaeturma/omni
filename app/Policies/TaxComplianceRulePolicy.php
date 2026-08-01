<?php

namespace App\Policies;

use App\Models\TaxComplianceRule;
use App\Models\User;

class TaxComplianceRulePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('tax-rules.view');
    }

    public function view(User $user, TaxComplianceRule $taxComplianceRule): bool
    {
        return $user->can('tax-rules.view');
    }

    public function create(User $user): bool
    {
        return $user->can('tax-rules.create');
    }

    public function update(User $user, TaxComplianceRule $taxComplianceRule): bool
    {
        return $user->can('tax-rules.update');
    }

    public function activate(User $user, TaxComplianceRule $taxComplianceRule): bool
    {
        return $user->can('tax-rules.activate');
    }

    public function deactivate(User $user, TaxComplianceRule $taxComplianceRule): bool
    {
        return $user->can('tax-rules.deactivate');
    }

    public function review(User $user, TaxComplianceRule $taxComplianceRule): bool
    {
        return $user->can('tax-rules.review');
    }

    public function delete(User $user, TaxComplianceRule $taxComplianceRule): bool
    {
        return false;
    }
}
