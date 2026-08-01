<?php

namespace App\Http\Requests;

use App\Models\FiscalPeriod;
use Illuminate\Foundation\Http\FormRequest;

class FinancialDashboardRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('financial-dashboard.view');
    }

    public function rules(): array
    {
        return [
            'fiscal_period_id' => ['required', 'integer', 'exists:fiscal_periods,id'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'as_of' => ['required', 'date', 'same:end_date'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $period = filled($this->input('fiscal_period_id'))
            ? FiscalPeriod::query()->find($this->integer('fiscal_period_id'))
            : FiscalPeriod::query()->whereDate('starts_on', '<=', now())->whereDate('ends_on', '>=', now())->first()
                ?? FiscalPeriod::query()->latest('ends_on')->first();

        if ($period instanceof FiscalPeriod) {
            $this->merge([
                'fiscal_period_id' => $period->id,
                'start_date' => $period->starts_on->toDateString(),
                'end_date' => $period->ends_on->toDateString(),
                'as_of' => $period->ends_on->toDateString(),
            ]);
        }
    }
}
