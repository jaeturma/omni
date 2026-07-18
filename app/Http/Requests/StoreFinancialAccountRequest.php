<?php

namespace App\Http\Requests;

use App\Enums\FinancialAccountType;
use App\Models\FinancialAccount;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreFinancialAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        $account = $this->route('financial_account');

        return $account instanceof FinancialAccount
            ? (bool) $this->user()?->can('update', $account)
            : (bool) $this->user()?->can('create', FinancialAccount::class);
    }

    public function rules(): array
    {
        $account = $this->route('financial_account');

        return ['code' => ['required', 'string', 'max:30', 'regex:/^[A-Z0-9_-]+$/', Rule::unique(FinancialAccount::class)->ignore($account)],
            'name' => ['required', 'string', 'max:255'], 'type' => ['required', Rule::enum(FinancialAccountType::class)],
            'bank_id' => ['nullable', 'integer', 'exists:banks,id'], 'branch_name' => ['nullable', 'string', 'max:255'],
            'account_number' => ['nullable', 'string', 'max:100'], 'account_holder_name' => ['nullable', 'string', 'max:255'],
            'currency' => ['required', 'string', 'size:3', 'regex:/^[A-Z]{3}$/'], 'opening_balance' => ['required', 'decimal:0,4'],
            'opening_balance_date' => ['nullable', 'date'], 'allow_receipts' => ['boolean'], 'allow_disbursements' => ['boolean'],
            'allow_transfers' => ['boolean'], 'allow_reconciliation' => ['boolean'], 'notes' => ['nullable', 'string', 'max:2000']];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if (bccomp((string) $this->input('opening_balance', '0'), '0', 4) !== 0 && blank($this->input('opening_balance_date'))) {
                $validator->errors()->add('opening_balance_date', 'The opening balance date is required when an opening balance is entered.');
            }
        }];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['code' => str($this->input('code'))->trim()->upper()->toString(), 'name' => str($this->input('name'))->squish()->toString(),
            'currency' => str($this->input('currency', 'PHP'))->trim()->upper()->toString(),
            'allow_receipts' => $this->boolean('allow_receipts'), 'allow_disbursements' => $this->boolean('allow_disbursements'),
            'allow_transfers' => $this->boolean('allow_transfers'), 'allow_reconciliation' => $this->boolean('allow_reconciliation')]);
    }
}
