<?php

namespace App\Http\Requests;

use App\Models\FiscalPeriod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TrialBalanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        $permission = $this->routeIs('subledger-reconciliations.*')
            ? 'subledger-reconciliation.view'
            : 'trial-balance.view';

        if ($this->routeIs('*.export')) {
            return $this->user()->can($permission)
                && $this->user()->can(str($permission)->beforeLast('.')->append('.export')->toString());
        }

        return $this->user()->can($permission);
    }

    public function rules(): array
    {
        return [
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'as_of' => ['required', 'date', 'after_or_equal:end_date'],
            'fiscal_period_id' => ['nullable', 'integer', 'exists:fiscal_periods,id'],
            'basis' => ['required', Rule::in(['unadjusted', 'adjusted'])],
            'account_id' => ['nullable', 'integer', 'exists:accounts,id'],
            'detail' => ['required', Rule::in(['postable', 'hierarchy'])],
        ];
    }

    protected function prepareForValidation(): void
    {
        $period = filled($this->input('fiscal_period_id'))
            ? FiscalPeriod::query()->find($this->integer('fiscal_period_id'))
            : null;
        $startDate = $period?->starts_on->toDateString()
            ?? $this->input('start_date', now()->startOfMonth()->toDateString());
        $endDate = $period?->ends_on->toDateString()
            ?? $this->input('end_date', now()->toDateString());

        $this->merge([
            'start_date' => $startDate,
            'end_date' => $endDate,
            'as_of' => $this->input('as_of', $endDate),
            'basis' => $this->input('basis', 'adjusted'),
            'detail' => $this->input('detail', 'postable'),
        ]);
    }
}
