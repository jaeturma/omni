<?php

namespace App\Services;

use App\Models\FiscalPeriod;
use App\Reports\AccountsPayableReport;
use App\Reports\BalanceSheetReport;
use App\Reports\CashFlowStatementReport;
use App\Reports\CashPositionReport;
use App\Reports\IncomeStatementReport;
use App\Reports\InventoryStockReport;
use App\Reports\OwnerEquityStatementReport;
use App\Reports\ReceivablesReport;
use App\Reports\TrialBalanceReport;

class FinancialReportPack
{
    public function __construct(
        private IncomeStatementReport $incomeStatement,
        private BalanceSheetReport $balanceSheet,
        private CashFlowStatementReport $cashFlow,
        private OwnerEquityStatementReport $ownerEquity,
        private TrialBalanceReport $trialBalance,
        private ReceivablesReport $receivables,
        private AccountsPayableReport $payables,
        private CashPositionReport $cashPosition,
        private InventoryStockReport $inventory,
    ) {}

    /** @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    public function generate(array $filters, FiscalPeriod $period): array
    {
        $statementFilters = $filters + ['report_view' => 'period', 'show_zero_balances' => false];
        $asOfFilters = ['as_of' => $filters['as_of'], 'fiscal_year_start' => (string) $period->fiscalYear->getRawOriginal('starts_on'),
            'fiscal_period_id' => $period->id, 'show_zero_balances' => false];
        $operational = $filters + ['financial_account_id' => null, 'account_type' => null, 'transaction_type' => null,
            'product_service_id' => null, 'category_id' => null, 'brand_id' => null, 'warehouse_id' => null, 'movement_type' => null];
        $arRows = $this->receivables->detailCollection(['as_of' => $filters['as_of']]);
        $apRows = $this->payables->detailCollection(['as_of' => $filters['as_of']]);

        $cashPosition = $this->cashPosition->summary($operational, true);
        $cashPosition['total'] = collect($cashPosition['positions'])->reduce(
            fn (string $total, array $row): string => bcadd($total, $row['as_of'], 4),
            '0.0000',
        );

        return [
            'income_statement' => $this->incomeStatement->generate($statementFilters),
            'balance_sheet' => $this->balanceSheet->generate($asOfFilters),
            'cash_flow_statement' => $this->cashFlow->generate($filters),
            'owner_equity_statement' => $this->ownerEquity->generate($filters),
            'trial_balance_summary' => $this->trialBalance->generate($filters + ['basis' => 'adjusted', 'detail' => 'postable', 'account_id' => null], false),
            'ar_aging_summary' => $this->agingTotals($arRows),
            'ap_aging_summary' => $this->agingTotals($apRows),
            'cash_position_summary' => $cashPosition,
            'inventory_valuation_summary' => $this->inventory->summary($operational, true),
        ];
    }

    /** @param iterable<array<string, mixed>> $rows
     * @return array<string, string>
     */
    private function agingTotals(iterable $rows): array
    {
        $totals = array_fill_keys(['current', '1-30', '31-60', '61-90', 'over-90', 'total'], '0.0000');
        foreach ($rows as $row) {
            $totals[$row['bucket']] = bcadd($totals[$row['bucket']], $row['balance'], 4);
            $totals['total'] = bcadd($totals['total'], $row['balance'], 4);
        }

        return $totals;
    }
}
