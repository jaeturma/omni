<?php

namespace App\Http\Requests;

use App\Enums\PettyCashVoucherStatus;
use App\Models\FiscalPeriod;
use App\Models\PettyCashFund;
use App\Models\PettyCashVoucher;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StorePettyCashReplenishmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $fund = PettyCashFund::find($this->integer('petty_cash_fund_id'));

        return $fund ? (bool) $this->user()?->can('replenish', $fund) : false;
    }

    public function rules(): array
    {
        return [
            'petty_cash_fund_id' => ['required', Rule::exists('petty_cash_funds', 'id')->where('active', true)],
            'source_financial_account_id' => ['required', Rule::exists('financial_accounts', 'id')->where('active', true)->where('allow_transfers', true)],
            'replenishment_date' => ['required', 'date'],
            'fiscal_period_id' => ['required', 'integer', 'exists:fiscal_periods,id'],
            'reference_number' => ['nullable', 'string', 'max:255'],
            'voucher_ids' => ['required', 'array', 'min:1'],
            'voucher_ids.*' => ['integer', 'distinct', 'exists:petty_cash_vouchers,id'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            $period = FiscalPeriod::find($this->integer('fiscal_period_id'));
            if (! $period || $period->status !== 'open' || ! $this->date('replenishment_date')?->betweenIncluded($period->starts_on, $period->ends_on)) {
                $validator->errors()->add('fiscal_period_id', 'Select the open fiscal period containing the replenishment date.');
            }
            $fund = PettyCashFund::with('financialAccount')->find($this->integer('petty_cash_fund_id'));
            if ($fund && (int) $fund->financial_account_id === $this->integer('source_financial_account_id')) {
                $validator->errors()->add('source_financial_account_id', 'The replenishment source must differ from the petty-cash account.');
            }
            $vouchers = PettyCashVoucher::query()->withCount('replenishments')->whereKey($this->input('voucher_ids', []))->get();
            foreach ($vouchers as $voucher) {
                if ((int) $voucher->petty_cash_fund_id !== $this->integer('petty_cash_fund_id')
                    || $voucher->status !== PettyCashVoucherStatus::Liquidated || $voucher->replenishments_count > 0) {
                    $validator->errors()->add('voucher_ids', 'Only unreplenished liquidated vouchers from this fund may be selected.');
                    break;
                }
            }
        }];
    }
}
