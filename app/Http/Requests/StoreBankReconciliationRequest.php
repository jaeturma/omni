<?php

namespace App\Http\Requests;

use App\Models\BankReconciliation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBankReconciliationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('create', BankReconciliation::class);
    }

    public function rules(): array
    {
        return ['bank_statement_import_id' => ['required', 'integer', Rule::unique(BankReconciliation::class)],
            'statement_opening_balance' => ['required', 'decimal:0,4'], 'statement_closing_balance' => ['required', 'decimal:0,4']];
    }
}
