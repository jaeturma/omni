<?php

namespace App\Http\Requests;

use App\Models\BankReconciliation;
use Illuminate\Foundation\Http\FormRequest;

class MatchBankReconciliationRequest extends FormRequest
{
    public function authorize(): bool
    {
        $reconciliation = $this->route('bank_reconciliation');

        return $reconciliation instanceof BankReconciliation && (bool) $this->user()?->can('match', $reconciliation);
    }

    public function rules(): array
    {
        return ['bank_statement_line_id' => ['required', 'integer', 'exists:bank_statement_lines,id'],
            'cash_transaction_ids' => ['required', 'array', 'min:1', 'max:20'], 'cash_transaction_ids.*' => ['required', 'integer', 'distinct', 'exists:cash_transactions,id']];
    }
}
