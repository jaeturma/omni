<?php

namespace App\Services;

use App\Enums\AccountType;
use App\Models\FiscalPeriod;
use App\Reports\AccountsPayableReport;
use App\Reports\BalanceSheetReport;
use App\Reports\CashPositionReport;
use App\Reports\IncomeStatementReport;
use App\Reports\ReceivablesReport;
use Carbon\CarbonImmutable;

class FinancialDashboard
{
    public function __construct(
        private IncomeStatementReport $incomeStatement,
        private BalanceSheetReport $balanceSheet,
        private ReceivablesReport $receivables,
        private AccountsPayableReport $payables,
        private CashPositionReport $cashPosition,
        private PeriodCloseChecklist $closeChecklist,
    ) {}

    /** @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    public function generate(array $filters, FiscalPeriod $period): array
    {
        $asOf = CarbonImmutable::parse($filters['as_of']);
        $statementFilters = $filters + ['report_view' => 'period', 'show_zero_balances' => false];
        $periodStatement = $this->incomeStatement->generate($statementFilters);
        $balanceSheet = $this->balanceSheet->generate([
            'as_of' => $filters['as_of'],
            'fiscal_year_start' => (string) $period->fiscalYear->getRawOriginal('starts_on'),
            'fiscal_period_id' => $period->id,
            'show_zero_balances' => false,
        ]);
        $monthStatement = $this->incomeStatement->generate($this->incomeFilters($asOf->startOfMonth(), $asOf));
        $quarterStatement = $this->incomeStatement->generate($this->incomeFilters($asOf->startOfQuarter(), $asOf));
        $receivables = $this->receivables->detailCollection(['as_of' => $filters['as_of']]);
        $payables = $this->payables->detailCollection(['as_of' => $filters['as_of']]);
        $cash = $this->cashPosition->summary($this->operationalFilters($filters), true);
        $checklist = $this->closeChecklist->generate($period);
        $critical = collect($checklist)->contains(fn (array $item): bool => ! $item['passed'] && $item['severity'] === 'critical');

        return [
            'metrics' => [
                'cash' => $this->balanceSheetAmount($balanceSheet, AccountType::Cash),
                'accounts_receivable' => $this->balanceSheetAmount($balanceSheet, AccountType::AccountsReceivable),
                'accounts_payable' => $this->balanceSheetAmount($balanceSheet, AccountType::AccountsPayable),
                'inventory_value' => $this->balanceSheetAmount($balanceSheet, AccountType::Inventory),
                'current_month_sales' => $monthStatement['summary']['net_sales'],
                'current_quarter_sales' => $quarterStatement['summary']['net_sales'],
                'gross_profit' => $periodStatement['summary']['gross_profit'],
                'operating_expenses' => $periodStatement['summary']['operating_expenses'],
                'net_income' => $periodStatement['summary']['net_income_after_tax'],
                'overdue_receivables' => $this->sum($receivables->where('daysOverdue', '>', 0), 'balance'),
                'overdue_payables' => $this->sum($payables->where('daysOverdue', '>', 0), 'balance'),
                'unreconciled_bank_items' => $cash['unreconciled'],
                'failed_accounting_postings' => (string) $checklist['failed_source_postings']['count'],
            ],
            'metrics_reliable' => ! $critical,
            'warnings' => collect($checklist)->reject(fn (array $item): bool => $item['passed'])->values(),
            'open_period_status' => $period->status,
            'generated_at' => now()->timezone(config('app.timezone')),
        ];
    }

    private function incomeFilters(CarbonImmutable $start, CarbonImmutable $end): array
    {
        return ['start_date' => $start->toDateString(), 'end_date' => $end->toDateString(), 'as_of' => $end->toDateString(),
            'fiscal_period_id' => null, 'report_view' => 'period', 'show_zero_balances' => false];
    }

    /** @param array<string, mixed> $filters */
    private function operationalFilters(array $filters): array
    {
        return $filters + ['financial_account_id' => null, 'account_type' => null, 'transaction_type' => null,
            'product_service_id' => null, 'category_id' => null, 'brand_id' => null, 'warehouse_id' => null, 'movement_type' => null];
    }

    /** @param iterable<array<string, mixed>> $rows */
    private function sum(iterable $rows, string $key): string
    {
        $total = '0.0000';
        foreach ($rows as $row) {
            $total = bcadd($total, (string) $row[$key], 4);
        }

        return $total;
    }

    /** @param array<string, mixed> $balanceSheet */
    private function balanceSheetAmount(array $balanceSheet, AccountType $accountType): string
    {
        return $balanceSheet['sections']->flatMap(fn (array $section) => $section['rows'])
            ->filter(fn (array $row): bool => $row['account']->is_postable && $row['account']->account_type === $accountType)
            ->reduce(fn (string $total, array $row): string => bcadd($total, $row['amount'], 4), '0.0000');
    }
}
