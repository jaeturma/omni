<?php

namespace App\Http\Requests;

use App\Models\BankReconciliation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TransitionBankReconciliationRequest extends FormRequest
{
    public function authorize(): bool
    {
        $reconciliation = $this->route('bank_reconciliation');
        $ability = $this->input('transition') === 'reopen' ? 'reopen' : 'finalize';

        return $reconciliation instanceof BankReconciliation && (bool) $this->user()?->can($ability, $reconciliation);
    }

    public function rules(): array
    {
        return ['transition' => ['required', Rule::in(['review', 'finalize', 'reopen'])],
            'reason' => [Rule::requiredIf($this->input('transition') === 'reopen'), 'nullable', 'string', 'max:2000']];
    }
}
