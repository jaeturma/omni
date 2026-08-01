<?php

namespace App\Reports;

use App\Enums\AccountingSourceType;
use App\Enums\JournalEntryStatus;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\InventoryBalance;
use App\Models\InventoryMovement;
use App\Models\JournalEntry;
use App\Models\ProductService;
use App\Models\SalesInvoice;
use App\Models\SalesInvoiceLine;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/** @phpstan-type ReportSection array{label: string, columns: list<string>, rows: Collection<int, array<string, mixed>>} */
class ManagementProfitabilityReport
{
    public function __construct(private IncomeStatementReport $incomeStatement) {}

    /** @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    public function generate(array $filters, bool $includeCosts): array
    {
        $income = $this->incomeStatement->generate($filters + ['show_zero_balances' => false]);
        $ledger = $income['summary'];
        $sections = match ($filters['report']) {
            'profitability' => $this->profitability($filters, $ledger, $includeCosts),
            'expenses' => $this->expenses($filters, $ledger),
            'collections' => $this->collections($filters),
            'turnover' => $this->turnover($filters, $ledger, $includeCosts),
            'trend' => $this->trend($filters, $includeCosts),
            default => $this->sales($filters, $ledger),
        };

        return [
            'title' => $this->title($filters['report']),
            'sections' => $sections,
            'source_note' => $this->sourceNote($filters['report']),
            'ledger' => [
                'net_sales' => $ledger['net_sales'],
                'operating_expenses' => $ledger['operating_expenses'],
                'cost_of_sales' => $ledger['cost_of_sales'],
                'net_income' => $ledger['net_income_after_tax'],
            ],
        ];
    }

    /** @param array<string, mixed> $filters
     * @return Collection<int, ReportSection>
     */
    private function sales(array $filters, array $ledger): Collection
    {
        $invoices = $this->invoiceQuery($filters);
        $operationalTotal = $this->decimal((clone $invoices)->sum('net_sales_amount'));
        $difference = bcsub($ledger['net_sales'], $operationalTotal, 4);

        $byCustomer = (clone $invoices)
            ->join((new Customer)->getTable().' as customers', 'customers.id', '=', 'sales_invoices.customer_id')
            ->selectRaw('customers.name AS label, COUNT(DISTINCT sales_invoices.id) AS transactions, COALESCE(SUM(sales_invoices.net_sales_amount), 0) AS amount')
            ->groupBy('customers.id', 'customers.name')->orderByDesc('amount')->toBase()->get();
        $byType = (clone $invoices)
            ->join((new Customer)->getTable().' as customers', 'customers.id', '=', 'sales_invoices.customer_id')
            ->selectRaw("CASE WHEN customers.type = 'government' THEN 'Government' ELSE 'Private' END AS label, COUNT(DISTINCT sales_invoices.id) AS transactions, COALESCE(SUM(sales_invoices.net_sales_amount), 0) AS amount")
            ->groupByRaw("CASE WHEN customers.type = 'government' THEN 'Government' ELSE 'Private' END")
            ->orderBy('label')->toBase()->get();
        $productRows = $this->salesLineQuery($filters)
            ->selectRaw('COALESCE(product_services.name, sales_invoice_lines.description) AS label, COUNT(DISTINCT sales_invoices.id) AS transactions, COALESCE(SUM(sales_invoice_lines.net_amount), 0) AS amount')
            ->groupBy('sales_invoice_lines.product_service_id', 'product_services.name', 'sales_invoice_lines.description')
            ->orderByDesc('amount')->toBase()->get();
        $categoryRows = $this->salesLineQuery($filters)
            ->selectRaw("COALESCE(categories.name, 'Uncategorized') AS label, COUNT(DISTINCT sales_invoices.id) AS transactions, COALESCE(SUM(sales_invoice_lines.net_amount), 0) AS amount")
            ->groupBy('categories.id', 'categories.name')->orderByDesc('amount')->toBase()->get();
        $serviceRows = $this->salesLineQuery($filters)->where('product_services.type', 'service')
            ->selectRaw("COALESCE(categories.name, 'Uncategorized Services') AS label, COUNT(DISTINCT sales_invoices.id) AS transactions, COALESCE(SUM(sales_invoice_lines.net_amount), 0) AS amount")
            ->groupBy('categories.id', 'categories.name')->orderByDesc('amount')->toBase()->get();

        return collect([
            $this->amountSection('Sales by Customer', $byCustomer),
            $this->amountSection('Sales by Customer Type / Market', $byType),
            $this->amountSection('Sales by Product or Service', $productRows),
            $this->amountSection('Sales by Product Category', $categoryRows),
            $this->amountSection('Sales by Service Category', $serviceRows),
            $this->section('Ledger Reconciliation', ['Source', 'Amount'], collect([
                ['label' => 'Operational posted sales', 'amount' => $operationalTotal],
                ['label' => 'Income statement net sales', 'amount' => $ledger['net_sales']],
                ['label' => 'Unattributed ledger / filtered difference', 'amount' => $difference],
            ]), ['label', 'amount']),
        ]);
    }

    /** @param array<string, mixed> $filters
     * @return Collection<int, ReportSection>
     */
    private function profitability(array $filters, array $ledger, bool $includeCosts): Collection
    {
        if (! $includeCosts) {
            return collect([$this->section('Restricted', ['Notice'], collect([
                ['notice' => 'Profit, margin, and cost details require profitability.view, margin.view, and cost-data.view.'],
            ]), ['notice'])]);
        }

        $base = $this->profitabilityQuery($filters);
        $byProduct = (clone $base)
            ->selectRaw('COALESCE(product_services.name, sales_invoice_lines.description) AS label, COALESCE(SUM(sales_invoice_lines.net_amount), 0) AS sales, COALESCE(-SUM(inventory_costs.total_cost), 0) AS cost')
            ->groupBy('sales_invoice_lines.product_service_id', 'product_services.name', 'sales_invoice_lines.description')->orderByDesc('sales')->toBase()->get();
        $byCategory = (clone $base)
            ->selectRaw("COALESCE(categories.name, 'Uncategorized') AS label, COALESCE(SUM(sales_invoice_lines.net_amount), 0) AS sales, COALESCE(-SUM(inventory_costs.total_cost), 0) AS cost")
            ->groupBy('categories.id', 'categories.name')->orderByDesc('sales')->toBase()->get();
        $byCustomer = (clone $base)
            ->selectRaw('customers.name AS label, COALESCE(SUM(sales_invoice_lines.net_amount), 0) AS sales, COALESCE(-SUM(inventory_costs.total_cost), 0) AS cost')
            ->groupBy('customers.id', 'customers.name')->orderByDesc('sales')->toBase()->get();

        $operationalSales = $byProduct->reduce(fn (string $total, object $row): string => bcadd($total, $this->decimal($row->sales), 4), '0.0000');
        $operationalCost = $byProduct->reduce(fn (string $total, object $row): string => bcadd($total, $this->decimal($row->cost), 4), '0.0000');
        $operationalProfit = bcsub($operationalSales, $operationalCost, 4);

        return collect([
            $this->profitSection('Gross Profit by Product or Service', $byProduct),
            $this->profitSection('Gross Profit by Category', $byCategory),
            $this->profitSection('Gross Profit by Customer', $byCustomer),
            $this->section('Ledger Reconciliation', ['Source', 'Sales', 'Cost', 'Gross Profit'], collect([
                ['label' => 'Operational dimensions', 'sales' => $operationalSales, 'cost' => $operationalCost, 'profit' => $operationalProfit],
                ['label' => 'Income statement', 'sales' => $ledger['net_sales'], 'cost' => $ledger['cost_of_sales'], 'profit' => $ledger['gross_profit']],
                ['label' => 'Unattributed ledger difference', 'sales' => bcsub($ledger['net_sales'], $operationalSales, 4), 'cost' => bcsub($ledger['cost_of_sales'], $operationalCost, 4), 'profit' => bcsub($ledger['gross_profit'], $operationalProfit, 4)],
            ]), ['label', 'sales', 'cost', 'profit']),
        ]);
    }

    /** @param array<string, mixed> $filters
     * @return Collection<int, ReportSection>
     */
    private function expenses(array $filters, array $ledger): Collection
    {
        $expenses = $this->expenseQuery($filters);
        $operationalTotal = $this->decimal((clone $expenses)->sum('gross_amount'));
        $byCategory = (clone $expenses)->selectRaw('expense_category AS label, COUNT(*) AS transactions, COALESCE(SUM(gross_amount), 0) AS amount')
            ->groupBy('expense_category')->orderByDesc('amount')->toBase()->get();
        $byPayee = (clone $expenses)->selectRaw('payee_name AS label, COUNT(*) AS transactions, COALESCE(SUM(gross_amount), 0) AS amount')
            ->groupBy('payee_name')->orderByDesc('amount')->toBase()->get();

        return collect([
            $this->amountSection('Expense by Category', $byCategory),
            $this->amountSection('Expense by Supplier or Payee', $byPayee),
            $this->section('Ledger Reconciliation', ['Source', 'Amount'], collect([
                ['label' => 'Operational posted expenses', 'amount' => $operationalTotal],
                ['label' => 'Income statement operating expenses', 'amount' => $ledger['operating_expenses']],
                ['label' => 'Unattributed ledger difference', 'amount' => bcsub($ledger['operating_expenses'], $operationalTotal, 4)],
            ]), ['label', 'amount']),
        ]);
    }

    /** @param array<string, mixed> $filters
     * @return Collection<int, ReportSection>
     */
    private function collections(array $filters): Collection
    {
        $rows = $this->invoiceQuery($filters)
            ->join((new Customer)->getTable().' as customers', 'customers.id', '=', 'sales_invoices.customer_id')
            ->selectRaw('customers.name AS label, COALESCE(SUM(sales_invoices.total_receivable), 0) AS billed, COALESCE(SUM(sales_invoices.paid_amount), 0) AS collected, COALESCE(SUM(sales_invoices.balance_due), 0) AS balance')
            ->groupBy('customers.id', 'customers.name')->orderByDesc('billed')->toBase()->get()
            ->map(function (object $row): array {
                $billed = $this->decimal($row->billed);
                $collected = $this->decimal($row->collected);

                return [
                    'label' => $row->label,
                    'billed' => $billed,
                    'collected' => $collected,
                    'balance' => $this->decimal($row->balance),
                    'rate' => $this->percentage($collected, $billed),
                ];
            });

        return collect([$this->section('Collection Performance by Customer', ['Customer', 'Billed', 'Collected', 'Balance', 'Collection %'], $rows, ['label', 'billed', 'collected', 'balance', 'rate'])]);
    }

    /** @param array<string, mixed> $filters
     * @return Collection<int, ReportSection>
     */
    private function turnover(array $filters, array $ledger, bool $includeCosts): Collection
    {
        $invoices = $this->invoiceQuery($filters);
        $sales = $this->decimal((clone $invoices)->sum('net_sales_amount'));
        $receivables = $this->decimal((clone $invoices)->sum('balance_due'));
        $days = CarbonImmutable::parse($filters['start_date'])->diffInDays(CarbonImmutable::parse($filters['end_date'])) + 1;
        $dso = bccomp($sales, '0', 4) === 0 ? null : bcdiv(bcmul($receivables, (string) $days, 4), $sales, 4);
        $sections = collect([$this->section('Receivable Turnover Indicators', ['Indicator', 'Value'], collect([
            ['label' => 'Operational net sales', 'value' => $sales],
            ['label' => 'Ending invoice receivables', 'value' => $receivables],
            ['label' => 'Days sales outstanding', 'value' => $dso ?? 'N/A'],
        ]), ['label', 'value'])]);

        if ($includeCosts) {
            $inventoryValue = InventoryBalance::query()->get(['quantity_on_hand', 'weighted_average_cost'])
                ->reduce(fn (string $total, InventoryBalance $balance): string => bcadd($total, bcmul($balance->quantity_on_hand, $balance->weighted_average_cost, 4), 4), '0.0000');
            $turnover = bccomp($inventoryValue, '0', 4) === 0 ? null : bcdiv($ledger['cost_of_sales'], $inventoryValue, 4);
            $sections->push($this->section('Inventory Turnover Indicators', ['Indicator', 'Value'], collect([
                ['label' => 'Ledger cost of sales', 'value' => $ledger['cost_of_sales']],
                ['label' => 'Current inventory value (not historical average)', 'value' => $inventoryValue],
                ['label' => 'Cost of sales / current inventory', 'value' => $turnover ?? 'N/A'],
            ]), ['label', 'value']));
        }

        return $sections;
    }

    /** @param array<string, mixed> $filters
     * @return Collection<int, ReportSection>
     */
    private function trend(array $filters, bool $includeCosts): Collection
    {
        $cursor = CarbonImmutable::parse($filters['start_date'])->startOfMonth();
        $end = CarbonImmutable::parse($filters['end_date']);
        $rows = collect();
        while ($cursor->lte($end)) {
            $monthEnd = $cursor->endOfMonth()->min($end);
            $monthStart = $cursor->max(CarbonImmutable::parse($filters['start_date']));
            $summary = $this->incomeStatement->generate([
                'start_date' => $monthStart->toDateString(), 'end_date' => $monthEnd->toDateString(), 'show_zero_balances' => false,
            ])['summary'];
            $row = ['month' => $cursor->format('Y-m'), 'net_sales' => $summary['net_sales']];
            if ($includeCosts) {
                $row += ['cost' => $summary['cost_of_sales'], 'gross_profit' => $summary['gross_profit'], 'expenses' => $summary['operating_expenses'], 'net_income' => $summary['net_income_after_tax']];
            }
            $rows->push($row);
            $cursor = $cursor->addMonth();
        }

        $columns = $includeCosts
            ? ['Month', 'Net Sales', 'Cost of Sales', 'Gross Profit', 'Operating Expenses', 'Net Income']
            : ['Month', 'Net Sales'];
        $keys = $includeCosts
            ? ['month', 'net_sales', 'cost', 'gross_profit', 'expenses', 'net_income']
            : ['month', 'net_sales'];

        return collect([$this->section('Monthly Profitability Trend', $columns, $rows, $keys)]);
    }

    /** @param array<string, mixed> $filters @return Builder<SalesInvoice> */
    private function invoiceQuery(array $filters): Builder
    {
        $query = SalesInvoice::query()
            ->whereIn('sales_invoices.id', $this->postedSourceIds(AccountingSourceType::SalesInvoice))
            ->whereBetween('sales_invoices.invoice_date', [$filters['start_date'], $filters['end_date']]);
        if ($filters['customer_id']) {
            $query->where('sales_invoices.customer_id', $filters['customer_id']);
        }
        if ($filters['category_id']) {
            $query->whereIn('sales_invoices.id', SalesInvoiceLine::query()
                ->whereIn('product_service_id', ProductService::query()->where('category_id', $filters['category_id'])->select('id'))
                ->select('sales_invoice_id'));
        }

        return $query;
    }

    /** @param array<string, mixed> $filters @return Builder<SalesInvoiceLine> */
    private function salesLineQuery(array $filters): Builder
    {
        $query = SalesInvoiceLine::query()
            ->join((new SalesInvoice)->getTable().' as sales_invoices', 'sales_invoices.id', '=', 'sales_invoice_lines.sales_invoice_id')
            ->leftJoin((new ProductService)->getTable().' as product_services', 'product_services.id', '=', 'sales_invoice_lines.product_service_id')
            ->leftJoin((new Category)->getTable().' as categories', 'categories.id', '=', 'product_services.category_id')
            ->whereIn('sales_invoices.id', $this->invoiceQuery($filters)->select('sales_invoices.id'));
        if ($filters['category_id']) {
            $query->where('product_services.category_id', $filters['category_id']);
        }

        return $query;
    }

    /** @param array<string, mixed> $filters @return Builder<SalesInvoiceLine> */
    private function profitabilityQuery(array $filters): Builder
    {
        $costs = InventoryMovement::query()->select('delivery_line_id')->selectRaw('SUM(total_cost) AS total_cost')->groupBy('delivery_line_id');

        return $this->salesLineQuery($filters)
            ->join((new Customer)->getTable().' as customers', 'customers.id', '=', 'sales_invoices.customer_id')
            ->leftJoinSub($costs, 'inventory_costs', fn ($join) => $join->on('inventory_costs.delivery_line_id', '=', 'sales_invoice_lines.delivery_line_id'));
    }

    /** @param array<string, mixed> $filters @return Builder<Expense> */
    private function expenseQuery(array $filters): Builder
    {
        return Expense::query()
            ->whereIn('expenses.id', $this->postedSourceIds(AccountingSourceType::Expense))
            ->whereBetween('expense_date', [$filters['start_date'], $filters['end_date']]);
    }

    /** @return Builder<JournalEntry> */
    private function postedSourceIds(AccountingSourceType $sourceType): Builder
    {
        return JournalEntry::query()->where('source_type', $sourceType)
            ->where('status', JournalEntryStatus::Posted)->whereNotNull('source_id')->select('source_id');
    }

    /** @param iterable<object> $rows
     * @return ReportSection
     */
    private function amountSection(string $label, iterable $rows): array
    {
        return $this->section($label, ['Dimension', 'Transactions', 'Net Sales / Expense'], collect($rows)->map(fn (object $row): array => [
            'label' => (string) $row->label,
            'transactions' => (string) $row->transactions,
            'amount' => $this->decimal($row->amount),
        ]), ['label', 'transactions', 'amount']);
    }

    /** @param iterable<object> $rows
     * @return ReportSection
     */
    private function profitSection(string $label, iterable $rows): array
    {
        return $this->section($label, ['Dimension', 'Sales', 'Cost', 'Gross Profit', 'Margin %'], collect($rows)->map(function (object $row): array {
            $sales = $this->decimal($row->sales);
            $cost = $this->decimal($row->cost);
            $profit = bcsub($sales, $cost, 4);

            return ['label' => (string) $row->label, 'sales' => $sales, 'cost' => $cost, 'profit' => $profit, 'margin' => $this->percentage($profit, $sales)];
        }), ['label', 'sales', 'cost', 'profit', 'margin']);
    }

    /** @param list<string> $columns
     * @param  iterable<array<string, mixed>|object>  $rows
     * @param  list<string>  $keys
     * @return ReportSection
     */
    private function section(string $label, array $columns, iterable $rows, array $keys): array
    {
        $selectedRows = collect($rows)->map(function (array|object $row) use ($keys): array {
            $values = (array) $row;
            $selected = [];
            foreach ($keys as $key) {
                $selected[$key] = $values[$key] ?? null;
            }

            return $selected;
        })->values();

        return ['label' => $label, 'columns' => $columns, 'rows' => $selectedRows];
    }

    private function percentage(string $numerator, string $denominator): string
    {
        return bccomp($denominator, '0', 4) === 0 ? 'N/A' : bcdiv(bcmul($numerator, '100', 4), $denominator, 2);
    }

    private function decimal(mixed $value): string
    {
        return bcadd('0', (string) ($value ?? '0'), 4);
    }

    private function title(string $report): string
    {
        return match ($report) {
            'profitability' => 'Gross Profit and Margin', 'expenses' => 'Expense Analysis',
            'collections' => 'Collection Performance', 'turnover' => 'Turnover Indicators',
            'trend' => 'Monthly Profitability Trend', default => 'Sales Analysis',
        };
    }

    private function sourceNote(string $report): string
    {
        return match ($report) {
            'sales', 'expenses', 'collections' => 'Operational dimensions limited to source documents that have posted ledger journals; ledger reconciliation differences remain visible.',
            'profitability' => 'Sales use posted invoices; inventory costs use delivery inventory movements. Per-line service cost is unavailable and therefore shown as zero, not estimated.',
            'turnover' => 'Receivable indicators use posted invoice balances. Inventory turnover uses current inventory value because historical average inventory is unavailable.',
            default => 'Monthly trend uses posted general-ledger activity and the same classifications as the income statement.',
        };
    }
}
