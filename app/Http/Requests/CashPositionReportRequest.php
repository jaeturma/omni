<?php

namespace App\Http\Requests;

use App\Enums\CashTransactionType;
use App\Enums\FinancialAccountType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class CashPositionReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('cash-reports.view');
    }

    public function rules(): array
    {
        return [
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'as_of' => ['required', 'date'],
            'financial_account_id' => ['nullable', 'integer', 'exists:financial_accounts,id'],
            'account_type' => ['nullable', Rule::enum(FinancialAccountType::class)],
            'transaction_type' => ['nullable', Rule::enum(CashTransactionType::class)],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($this->date('end_date')?->isAfter($this->date('as_of'))) {
                $validator->errors()->add('as_of', 'The as-of date must be on or after the range end date.');
            }
        }];
    }

    protected function prepareForValidation(): void
    {
        $asOf = $this->input('as_of', now()->toDateString());
        $this->merge([
            'as_of' => $asOf,
            'start_date' => $this->input('start_date', now()->startOfMonth()->toDateString()),
            'end_date' => $this->input('end_date', $asOf),
            'financial_account_id' => $this->filled('financial_account_id') ? $this->input('financial_account_id') : null,
            'account_type' => $this->filled('account_type') ? $this->input('account_type') : null,
            'transaction_type' => $this->filled('transaction_type') ? $this->input('transaction_type') : null,
        ]);
    }
}
