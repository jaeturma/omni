<?php

namespace App\Http\Requests;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ComparativeReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        if (! $this->user()?->can('comparative-reports.view')) {
            return false;
        }

        return match (true) {
            $this->routeIs('comparative-reports.print') => (bool) $this->user()->can('financial-reports.print'),
            $this->routeIs('comparative-reports.export') => (bool) ($this->user()->can('comparative-reports.export') && $this->user()->can('financial-reports.export')),
            default => true,
        };
    }

    public function rules(): array
    {
        return [
            'report_type' => ['required', Rule::in(['income_statement', 'balance_sheet'])],
            'comparison_type' => ['required', Rule::in(['prior_month', 'prior_quarter', 'prior_year', 'prior_ytd', 'custom'])],
            'reference_date' => ['required', 'date'],
            'current_start_date' => ['required', 'date'],
            'current_end_date' => ['required', 'date', 'after_or_equal:current_start_date'],
            'comparison_start_date' => ['required', 'date', 'before_or_equal:comparison_end_date'],
            'comparison_end_date' => ['required', 'date', 'before:current_start_date'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $reference = CarbonImmutable::parse($this->input('reference_date', now()->toDateString()));
        $comparisonType = $this->input('comparison_type', 'prior_month');
        [$currentStart, $currentEnd, $comparisonStart, $comparisonEnd] = match ($comparisonType) {
            'prior_quarter' => $this->priorQuarter($reference),
            'prior_year' => $this->priorYear($reference),
            'prior_ytd' => $this->priorYearToDate($reference),
            'custom' => $this->customPeriods($reference),
            default => $this->priorMonth($reference),
        };

        $this->merge([
            'report_type' => $this->input('report_type', 'income_statement'),
            'comparison_type' => $comparisonType,
            'reference_date' => $reference->toDateString(),
            'current_start_date' => $currentStart,
            'current_end_date' => $currentEnd,
            'comparison_start_date' => $comparisonStart,
            'comparison_end_date' => $comparisonEnd,
        ]);
    }

    /** @return array{string, string, string, string} */
    private function priorMonth(CarbonImmutable $reference): array
    {
        $currentStart = $reference->startOfMonth();
        $comparisonStart = $currentStart->subMonthNoOverflow();

        return [$currentStart->toDateString(), $reference->toDateString(), $comparisonStart->toDateString(), $comparisonStart->endOfMonth()->toDateString()];
    }

    /** @return array{string, string, string, string} */
    private function priorQuarter(CarbonImmutable $reference): array
    {
        $currentStart = $reference->startOfQuarter();
        $comparisonStart = $currentStart->subQuarter();

        return [$currentStart->toDateString(), $reference->toDateString(), $comparisonStart->toDateString(), $comparisonStart->endOfQuarter()->toDateString()];
    }

    /** @return array{string, string, string, string} */
    private function priorYear(CarbonImmutable $reference): array
    {
        $currentStart = $reference->startOfMonth();
        $comparisonStart = $currentStart->subYear();

        return [$currentStart->toDateString(), $reference->toDateString(), $comparisonStart->toDateString(), $reference->subYear()->toDateString()];
    }

    /** @return array{string, string, string, string} */
    private function priorYearToDate(CarbonImmutable $reference): array
    {
        return [
            $reference->startOfYear()->toDateString(),
            $reference->toDateString(),
            $reference->subYear()->startOfYear()->toDateString(),
            $reference->subYear()->toDateString(),
        ];
    }

    /** @return array{string, string, string, string} */
    private function customPeriods(CarbonImmutable $reference): array
    {
        return [
            (string) $this->input('current_start_date', $reference->startOfMonth()->toDateString()),
            (string) $this->input('current_end_date', $reference->toDateString()),
            (string) $this->input('comparison_start_date', $reference->subMonth()->startOfMonth()->toDateString()),
            (string) $this->input('comparison_end_date', $reference->subMonth()->endOfMonth()->toDateString()),
        ];
    }
}
