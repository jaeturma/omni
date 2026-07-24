<?php

namespace App\Actions;

use App\Models\BankReconciliation;
use App\Models\BankStatementImport;
use App\Models\User;
use App\Services\BankReconciliationCalculator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateBankReconciliation
{
    public function __construct(private BankReconciliationCalculator $calculator) {}

    public function handle(array $data, User $user): BankReconciliation
    {
        return DB::transaction(function () use ($data, $user): BankReconciliation {
            $import = BankStatementImport::query()->with('financialAccount')->lockForUpdate()->findOrFail($data['bank_statement_import_id']);
            if ($import->rolled_back_at || $import->finalized_at || $import->financialAccount->allow_reconciliation !== true) {
                throw ValidationException::withMessages(['bank_statement_import_id' => 'The selected statement import is not available for reconciliation.']);
            }
            [$opening, $closing] = $this->calculator->balances($import);
            $reconciliation = BankReconciliation::query()->create([
                'bank_statement_import_id' => $import->id, 'financial_account_id' => $import->financial_account_id,
                'statement_start_date' => $import->statement_start_date, 'statement_end_date' => $import->statement_end_date,
                'statement_opening_balance' => $data['statement_opening_balance'], 'statement_closing_balance' => $data['statement_closing_balance'],
                'system_opening_balance' => $opening, 'system_closing_balance' => $closing,
                'reconciliation_difference' => '0.0000', 'created_by' => $user->id,
            ]);
            $this->calculator->refresh($reconciliation);

            return $reconciliation;
        });
    }
}
