<?php

namespace App\Http\Requests;

use App\Enums\PettyCashVoucherStatus;
use App\Models\PettyCashFund;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdatePettyCashFundRequest extends FormRequest
{
    public function authorize(): bool
    {
        $fund = $this->route('petty_cash_fund');

        return $fund instanceof PettyCashFund && (bool) $this->user()?->can('update', $fund);
    }

    public function rules(): array
    {
        return [
            'custodian_id' => ['required', Rule::exists('users', 'id')->where('active', true)],
            'approved_fund_limit' => ['required', 'decimal:0,4', 'gt:0'],
            'active' => ['required', 'boolean'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            $fund = $this->route('petty_cash_fund');
            if (! $fund instanceof PettyCashFund) {
                return;
            }
            if (bccomp((string) $this->input('approved_fund_limit'), $fund->current_operational_balance, 4) < 0) {
                $validator->errors()->add('approved_fund_limit', 'The approved limit cannot be below the current operational balance.');
            }
            if (! $this->boolean('active') && $fund->vouchers()->whereIn('status', [PettyCashVoucherStatus::Released, PettyCashVoucherStatus::Overdue])->exists()) {
                $validator->errors()->add('active', 'Liquidate or void all outstanding vouchers before deactivating the fund.');
            }
        }];
    }
}
