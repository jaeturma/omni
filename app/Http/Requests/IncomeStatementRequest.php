<?php

namespace App\Http\Requests;

use App\Models\FiscalPeriod;
use App\Models\FiscalYear;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IncomeStatementRequest extends FormRequest
{
    public function authorize(): bool
    {
        if (! $this->user()?->can('income-statement.view')) {
            return false;
        }

        return match (true) {
            $this->routeIs('income-statement.print') => (bool) $this->user()->can('financial-reports.print'),
            $this->routeIs('income-statement.export') => (bool) ($this->user()->can('income-statement.export') && $this->user()->can('financial-reports.export')),
            $this->routeIs('income-statement.drilldown') => (bool) ($this->user()->can('income-statement.drilldown') && $this->user()->can('financial-reports.drilldown')),
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
            'report_view' => ['required', Rule::in(['period', 'year_to_date'])],
            'show_zero_balances' => ['required', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $period = filled($this->input('fiscal_period_id'))
            ? FiscalPeriod::query()->with('fiscalYear:id,starts_on')->find($this->integer('fiscal_period_id'))
            : null;
        $reportView = $this->input('report_view', 'period');
        $endDate = $period?->ends_on->toDateString() ?? $this->input('end_date', now()->toDateString());
        $fiscalYearStart = ($period instanceof FiscalPeriod ? $period->fiscalYear->getRawOriginal('starts_on') : null)
            ?? FiscalYear::query()
                ->whereDate('starts_on', '<=', $endDate)
                ->whereDate('ends_on', '>=', $endDate)
                ->value('starts_on');
        $startDate = $reportView === 'year_to_date'
            ? ($fiscalYearStart === null ? now()->parse($endDate)->startOfYear()->toDateString() : (string) $fiscalYearStart)
            : ($period?->starts_on->toDateString() ?? $this->input('start_date', now()->startOfMonth()->toDateString()));

        $this->merge([
            'start_date' => $startDate,
            'end_date' => $endDate,
            'as_of' => $endDate,
            'report_view' => $reportView,
            'show_zero_balances' => $this->boolean('show_zero_balances'),
        ]);
    }
}
