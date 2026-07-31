<?php

namespace App\Http\Requests;

use App\Models\FiscalPeriod;
use App\Models\FiscalYear;
use Illuminate\Foundation\Http\FormRequest;

class BalanceSheetRequest extends FormRequest
{
    public function authorize(): bool
    {
        if (! $this->user()?->can('balance-sheet.view')) {
            return false;
        }

        return match (true) {
            $this->routeIs('balance-sheet.export') => (bool) $this->user()->can('balance-sheet.export'),
            $this->routeIs('balance-sheet.drilldown') => (bool) $this->user()->can('balance-sheet.drilldown'),
            default => true,
        };
    }

    public function rules(): array
    {
        return [
            'as_of' => ['required', 'date'],
            'fiscal_year_start' => ['required', 'date', 'before_or_equal:as_of'],
            'fiscal_period_id' => ['nullable', 'integer', 'exists:fiscal_periods,id'],
            'show_zero_balances' => ['required', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $period = filled($this->input('fiscal_period_id'))
            ? FiscalPeriod::query()->with('fiscalYear:id,starts_on')->find($this->integer('fiscal_period_id'))
            : null;
        $asOf = $period?->ends_on->toDateString() ?? $this->input('as_of', now()->toDateString());
        $fiscalYearStart = $period instanceof FiscalPeriod
            ? $period->fiscalYear->getRawOriginal('starts_on')
            : ($this->input('fiscal_year_start') ?? FiscalYear::query()
                ->whereDate('starts_on', '<=', $asOf)
                ->whereDate('ends_on', '>=', $asOf)
                ->value('starts_on')
                ?? now()->parse($asOf)->startOfYear()->toDateString());

        $this->merge([
            'as_of' => $asOf,
            'fiscal_year_start' => (string) $fiscalYearStart,
            'show_zero_balances' => $this->boolean('show_zero_balances'),
        ]);
    }
}
