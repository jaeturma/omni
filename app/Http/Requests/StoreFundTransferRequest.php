<?php

namespace App\Http\Requests;

use App\Models\FinancialAccount;
use App\Models\FiscalPeriod;
use App\Models\FundTransfer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreFundTransferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('create', FundTransfer::class);
    }

    public function rules(): array
    {
        return [
            'transfer_date' => ['required', 'date'],
            'destination_date' => ['required', 'date', 'after_or_equal:transfer_date'],
            'fiscal_period_id' => ['required', 'integer', 'exists:fiscal_periods,id'],
            'source_financial_account_id' => ['required', 'different:destination_financial_account_id', Rule::exists('financial_accounts', 'id')->where('active', true)->where('allow_transfers', true)],
            'destination_financial_account_id' => ['required', 'different:source_financial_account_id', Rule::exists('financial_accounts', 'id')->where('active', true)->where('allow_transfers', true)],
            'amount' => ['required', 'decimal:0,4', 'gt:0'],
            'transfer_fee' => ['nullable', 'decimal:0,4', 'gte:0'],
            'reference_number' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            $period = FiscalPeriod::find($this->integer('fiscal_period_id'));
            if (! $period || $period->status !== 'open'
                || ! $this->date('transfer_date')?->betweenIncluded($period->starts_on, $period->ends_on)
                || ! $this->date('destination_date')?->betweenIncluded($period->starts_on, $period->ends_on)) {
                $validator->errors()->add('fiscal_period_id', 'Both transfer dates must belong to the selected open fiscal period.');
            }

            $source = FinancialAccount::find($this->integer('source_financial_account_id'));
            $destination = FinancialAccount::find($this->integer('destination_financial_account_id'));
            if ($source && $destination && $source->currency !== $destination->currency) {
                $validator->errors()->add('destination_financial_account_id', 'Transfers require accounts with the same currency.');
            }
        }];
    }
}
