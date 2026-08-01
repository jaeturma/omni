<?php

namespace App\Reports;

use App\Models\FiscalYear;
use App\Support\FinancialReportingConvention;
use Carbon\CarbonImmutable;

class ComparativeFinancialReport
{
    public function __construct(
        private IncomeStatementReport $incomeStatement,
        private BalanceSheetReport $balanceSheet,
    ) {}

    /** @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    public function generate(array $filters): array
    {
        $current = $this->statement($filters, true);
        $comparison = $this->statement($filters, false);
        $comparisonSections = $comparison['sections']->keyBy('key');
        $sections = $current['sections']->map(function (array $section) use ($comparisonSections): array {
            $comparisonSection = $comparisonSections->get($section['key']);
            $comparisonRows = collect($comparisonSection['rows'])->keyBy(fn (array $row): int => $row['account']->id);
            $rows = collect($section['rows'])->map(fn (array $row): array => $this->comparisonRow(
                $row,
                $comparisonRows->get($row['account']->id)['amount'] ?? '0.0000',
            ));

            return [
                'key' => $section['key'],
                'label' => $section['label'],
                'rows' => $rows,
                ...$this->variance($section['total'], $comparisonSection['total']),
            ];
        });

        return [
            'sections' => $sections,
            'current_label' => $this->periodLabel($filters['current_start_date'], $filters['current_end_date']),
            'comparison_label' => $this->periodLabel($filters['comparison_start_date'], $filters['comparison_end_date']),
            'report_label' => $filters['report_type'] === 'balance_sheet' ? 'Balance Sheet' : 'Income Statement',
            'mapping_rule' => FinancialReportingConvention::COMPARATIVE_MAPPING_RULE,
        ];
    }

    /** @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    private function statement(array $filters, bool $current): array
    {
        $prefix = $current ? 'current' : 'comparison';
        $start = $filters[$prefix.'_start_date'];
        $end = $filters[$prefix.'_end_date'];

        if ($filters['report_type'] === 'balance_sheet') {
            return $this->balanceSheet->generate([
                'as_of' => $end,
                'fiscal_year_start' => $this->fiscalYearStart($end),
                'fiscal_period_id' => null,
                'show_zero_balances' => true,
            ]);
        }

        return $this->incomeStatement->generate([
            'start_date' => $start,
            'end_date' => $end,
            'as_of' => $end,
            'fiscal_period_id' => null,
            'report_view' => 'period',
            'show_zero_balances' => true,
        ]);
    }

    /** @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function comparisonRow(array $row, string $comparison): array
    {
        return $row + $this->variance($row['amount'], $comparison);
    }

    /** @return array<string, string|null> */
    private function variance(string $current, string $comparison): array
    {
        $absolute = bcsub($current, $comparison, 4);
        $percentage = bccomp($comparison, '0', 4) === 0
            ? null
            : bcdiv(bcmul($absolute, '100', 6), $this->absolute($comparison), 2);

        return [
            'current_amount' => $current,
            'comparison_amount' => $comparison,
            'absolute_variance' => $absolute,
            'percentage_variance' => $percentage,
            'display_current_amount' => FinancialReportingConvention::round($current),
            'display_comparison_amount' => FinancialReportingConvention::round($comparison),
            'display_absolute_variance' => FinancialReportingConvention::round($absolute),
            'display_percentage_variance' => $percentage === null ? null : $percentage.'%',
        ];
    }

    private function absolute(string $amount): string
    {
        return bccomp($amount, '0', 4) < 0 ? bcsub('0', $amount, 4) : $amount;
    }

    private function fiscalYearStart(string $date): string
    {
        return (string) (FiscalYear::query()
            ->whereDate('starts_on', '<=', $date)
            ->whereDate('ends_on', '>=', $date)
            ->value('starts_on') ?? CarbonImmutable::parse($date)->startOfYear()->toDateString());
    }

    private function periodLabel(string $start, string $end): string
    {
        return CarbonImmutable::parse($start)->isoFormat('MMM D, YYYY').' – '.CarbonImmutable::parse($end)->isoFormat('MMM D, YYYY');
    }
}
