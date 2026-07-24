<?php

namespace App\Http\Requests;

use App\Enums\FinancialAccountType;
use App\Models\FinancialAccount;
use App\Models\PettyCashFund;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StorePettyCashFundRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('create', PettyCashFund::class);
    }

    public function rules(): array
    {
        return [
            'financial_account_id' => ['required', Rule::exists('financial_accounts', 'id')->where('type', FinancialAccountType::PettyCash)->where('active', true), 'unique:petty_cash_funds,financial_account_id'],
            'custodian_id' => ['required', Rule::exists('users', 'id')->where('active', true)],
            'approved_fund_limit' => ['required', 'decimal:0,4', 'gt:0'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            $account = FinancialAccount::find($this->integer('financial_account_id'));
            if ($account && bccomp($account->current_balance ?? $account->opening_balance, (string) $this->input('approved_fund_limit'), 4) > 0) {
                $validator->errors()->add('approved_fund_limit', 'The approved limit cannot be below the account operational balance.');
            }
        }];
    }
}
