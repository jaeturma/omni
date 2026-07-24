<?php

namespace App\Policies;

use App\Models\BankStatementImport;
use App\Models\User;

class BankStatementImportPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('bank-statements.view');
    }

    public function view(User $user, BankStatementImport $import): bool
    {
        return $user->can('bank-statements.view');
    }

    public function create(User $user): bool
    {
        return $user->can('bank-statements.import');
    }

    public function rollback(User $user, BankStatementImport $import): bool
    {
        return $user->can('bank-statements.rollback') && $import->finalized_at === null && $import->rolled_back_at === null;
    }

    public function export(User $user, BankStatementImport $import): bool
    {
        return $user->can('bank-statements.export') && $import->rolled_back_at === null;
    }
}
