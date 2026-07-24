<?php

namespace App\Actions;

use App\Models\BankStatementImport;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RollbackBankStatementImport
{
    public function handle(BankStatementImport $import, User $user): void
    {
        DB::transaction(function () use ($import, $user): void {
            $locked = BankStatementImport::query()->lockForUpdate()->findOrFail($import->id);
            if ($locked->finalized_at !== null || $locked->rolled_back_at !== null) {
                throw ValidationException::withMessages(['statement' => 'Only an active, unfinalized statement import can be rolled back.']);
            }
            $locked->lines()->delete();
            $locked->update(['rolled_back_at' => now(), 'rolled_back_by' => $user->id]);
        });
    }
}
