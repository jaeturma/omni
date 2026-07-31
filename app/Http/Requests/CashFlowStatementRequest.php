<?php

namespace App\Http\Requests;

use App\Models\FiscalPeriod;
use Illuminate\Foundation\Http\FormRequest;

class CashFlowStatementRequest extends FormRequest
{
    public function authorize(): bool
    {
        if (! $this->user()?->can('cash-flow-statement.view')) {
            return false;
        }

        return match (true) {
            $this->routeIs('cash-flow-statement.export') => (bool) $this->user()->can('cash-flow-statement.export'),
            $this->routeIs('cash-flow-statement.drilldown') => (bool) $this->user()->can('cash-flow-statement.drilldown'),
            $this->routeIs('cash-flow-statement.mappings') => (bool) $this->user()->can('cash-flow-mapping.manage'),
            default => true,
        };
    }

    public function rules(): array
    {
        return [
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'as_of' => ['required', 'date', 'same:end_date'],
            'fiscal_period_id' => ['nullable', 'integer', 'exists:fiscal_periods,id'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $period = filled($this->input('fiscal_period_id'))
            ? FiscalPeriod::query()->find($this->integer('fiscal_period_id'))
            : null;
        $endDate = $period?->ends_on->toDateString() ?? $this->input('end_date', now()->toDateString());
        $startDate = $period?->starts_on->toDateString()
            ?? $this->input('start_date', now()->startOfMonth()->toDateString());

        $this->merge([
            'start_date' => $startDate,
            'end_date' => $endDate,
            'as_of' => $endDate,
        ]);
    }
}
