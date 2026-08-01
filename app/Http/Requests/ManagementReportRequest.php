<?php

namespace App\Http\Requests;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ManagementReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        if (! $this->user()?->can('management-reports.view')) {
            return false;
        }

        return match (true) {
            $this->routeIs('management-reports.print') => (bool) $this->user()->can('financial-reports.print'),
            $this->routeIs('management-reports.export') => (bool) ($this->user()->can('management-reports.export') && $this->user()->can('financial-reports.export')),
            default => true,
        };
    }

    public function rules(): array
    {
        return [
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'report' => ['required', Rule::in(['sales', 'profitability', 'expenses', 'collections', 'turnover', 'trend'])],
        ];
    }

    /** @return array<callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $start = CarbonImmutable::parse($this->input('start_date'));
            $end = CarbonImmutable::parse($this->input('end_date'));
            if ($start->diffInMonths($end) > 23) {
                $validator->errors()->add('end_date', 'Management reports are limited to 24 months per run.');
            }
        }];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'start_date' => $this->input('start_date', now()->startOfMonth()->toDateString()),
            'end_date' => $this->input('end_date', now()->toDateString()),
            'customer_id' => $this->filled('customer_id') ? $this->input('customer_id') : null,
            'category_id' => $this->filled('category_id') ? $this->input('category_id') : null,
            'report' => $this->input('report', 'sales'),
        ]);
    }
}
