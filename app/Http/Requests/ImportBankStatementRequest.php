<?php

namespace App\Http\Requests;

use App\Enums\FinancialAccountType;
use App\Models\BankStatementImport;
use App\Models\FinancialAccount;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ImportBankStatementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('create', BankStatementImport::class);
    }

    public function rules(): array
    {
        return [
            'financial_account_id' => ['required', 'integer', Rule::exists(FinancialAccount::class, 'id')->where(fn ($query) => $query->where('active', true)->where('allow_reconciliation', true)->whereIn('type', [
                FinancialAccountType::BankChecking->value, FinancialAccountType::BankSavings->value, FinancialAccountType::EWallet->value,
            ]))],
            'statement_start_date' => ['required', 'date'],
            'statement_end_date' => ['required', 'date', 'after_or_equal:statement_start_date'],
            'statement_file' => ['required', 'file', 'mimes:csv,txt', 'max:5120'],
            'date_format' => ['required', Rule::in(['Y-m-d', 'm/d/Y', 'd/m/Y', 'm/d/y', 'd-m-Y'])],
            'transaction_date_column' => ['required', 'string', 'max:255'],
            'posting_date_column' => ['nullable', 'string', 'max:255'],
            'description_column' => ['required', 'string', 'max:255'],
            'reference_number_column' => ['nullable', 'string', 'max:255'],
            'debit_column' => ['required', 'string', 'max:255', 'different:credit_column'],
            'credit_column' => ['required', 'string', 'max:255', 'different:debit_column'],
            'running_balance_column' => ['nullable', 'string', 'max:255'],
        ];
    }

    protected function prepareForValidation(): void
    {
        foreach (['transaction_date_column', 'posting_date_column', 'description_column', 'reference_number_column', 'debit_column', 'credit_column', 'running_balance_column'] as $field) {
            $this->merge([$field => filled($this->input($field)) ? trim((string) $this->input($field)) : null]);
        }
    }
}
