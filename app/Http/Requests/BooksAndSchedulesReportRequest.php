<?php

namespace App\Http\Requests;

use App\Services\BooksAndSchedules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BooksAndSchedulesReportRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $report = (string) $this->input('report', $this->routeIs('tax-schedules.*') ? 'withholding_certificates' : 'general_journal');
        $group = BooksAndSchedules::REPORTS[$report]['group'] ?? 'books';
        $action = $this->routeIs('*.export') ? 'export' : 'view';

        return (bool) $this->user()?->can(($group === 'books' ? 'books-of-accounts.' : 'tax-schedules.').$action);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'report' => ['required', Rule::in(array_keys(BooksAndSchedules::REPORTS))],
            'start_date' => ['nullable', 'date'], 'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'fiscal_year_id' => ['nullable', 'integer', 'exists:fiscal_years,id'],
            'tax_period_id' => ['nullable', 'integer', 'exists:tax_periods,id'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['report' => $this->input('report', $this->routeIs('tax-schedules.*') ? 'withholding_certificates' : 'general_journal'), 'start_date' => $this->input('start_date', now()->startOfYear()->toDateString()), 'end_date' => $this->input('end_date', now()->endOfYear()->toDateString())]);
    }
}
