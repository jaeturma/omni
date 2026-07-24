<?php

namespace App\Http\Requests;

use App\Models\Expense;
use App\Models\FiscalPeriod;
use App\Models\PettyCashFund;
use App\Models\PettyCashVoucher;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StorePettyCashVoucherRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('create', PettyCashVoucher::class);
    }

    public function rules(): array
    {
        return [
            'petty_cash_fund_id' => ['required', Rule::exists('petty_cash_funds', 'id')->where('active', true)],
            'voucher_date' => ['required', 'date'],
            'fiscal_period_id' => ['required', 'integer', 'exists:fiscal_periods,id'],
            'payee' => ['required', 'string', 'max:255'],
            'expense_category' => ['required', Rule::in(Expense::CATEGORIES)],
            'purpose' => ['required', 'string', 'max:5000'],
            'amount_released' => ['required', 'decimal:0,4', 'gt:0'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            $period = FiscalPeriod::find($this->integer('fiscal_period_id'));
            if (! $period || $period->status !== 'open' || ! $this->date('voucher_date')?->betweenIncluded($period->starts_on, $period->ends_on)) {
                $validator->errors()->add('fiscal_period_id', 'Select the open fiscal period containing the voucher date.');
            }
            $fund = PettyCashFund::find($this->integer('petty_cash_fund_id'));
            if ($fund && bccomp((string) $this->input('amount_released'), $fund->approved_fund_limit, 4) > 0) {
                $validator->errors()->add('amount_released', 'The release cannot exceed the approved fund limit.');
            }
        }];
    }
}
