<?php

namespace App\Http\Requests;

use App\Models\BankReconciliation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateReconciliationAdjustmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $reconciliation = $this->route('bank_reconciliation');

        return $reconciliation instanceof BankReconciliation && (bool) $this->user()?->can('match', $reconciliation);
    }

    public function rules(): array
    {
        return ['bank_statement_line_id' => ['required', 'integer', 'exists:bank_statement_lines,id'],
            'kind' => ['required', Rule::in(['bank_charge', 'interest_other'])]];
    }
}
