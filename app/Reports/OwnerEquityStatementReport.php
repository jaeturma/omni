<?php

namespace App\Reports;

use App\Enums\AccountClass;
use App\Enums\AccountType;
use App\Enums\JournalEntryType;
use App\Models\FiscalYear;
use App\Models\JournalEntryLine;
use App\Support\AccountingWorkflow;
use App\Support\FinancialReportingConvention;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class OwnerEquityStatementReport
{
    private const ACTIVITIES = [
        'contributions' => 'Additional Capital Contributions',
        'net_income' => 'Net Income or Loss',
        'drawings' => "Owner's Drawings",
        'prior_period_adjustments' => 'Prior-period Adjustments',
    ];

    public function __construct(
        private IncomeStatementReport $incomeStatement,
        private BalanceSheetReport $balanceSheet,
    ) {}

    /** @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    public function generate(array $filters): array
    {
        $beginningDate = CarbonImmutable::parse($filters['start_date'])->subDay()->toDateString();
        $beginningEquity = $this->balanceSheetEquity($beginningDate);
        $contributions = $this->activityTotal($filters, 'contributions');
        $netIncome = $this->netIncome($filters);
        $drawings = $this->activityTotal($filters, 'drawings');
        $priorAdjustments = $this->activityTotal($filters, 'prior_period_adjustments');
        $closingEquity = bcadd(
            bcadd($beginningEquity, $contributions, 4),
            bcadd($netIncome, bcadd($drawings, $priorAdjustments, 4), 4),
            4,
        );
        $balanceSheetClosing = $this->balanceSheetEquity($filters['end_date']);
        $difference = bcsub($closingEquity, $balanceSheetClosing, 4);
        $reconciled = AccountingWorkflow::isBalanced($closingEquity, $balanceSheetClosing);
        $rows = collect([
            $this->row('beginning_equity', "Beginning Owner's Equity", $beginningEquity, false),
            $this->row('contributions', self::ACTIVITIES['contributions'], $contributions),
            $this->row('net_income', self::ACTIVITIES['net_income'], $netIncome),
            $this->row('drawings', self::ACTIVITIES['drawings'], $drawings),
            $this->row('prior_period_adjustments', self::ACTIVITIES['prior_period_adjustments'], $priorAdjustments),
            $this->row('closing_equity', "Closing Owner's Equity", $closingEquity, false),
        ]);
        $summary = [
            'beginning_equity' => $beginningEquity,
            'contributions' => $contributions,
            'net_income' => $netIncome,
            'drawings' => $drawings,
            'prior_period_adjustments' => $priorAdjustments,
            'closing_equity' => $closingEquity,
            'balance_sheet_closing_equity' => $balanceSheetClosing,
            'reconciliation_difference' => $difference,
        ];

        return [
            'rows' => $rows,
            'summary' => $summary,
            'display_summary' => collect($summary)
                ->map(fn (string $amount): string => FinancialReportingConvention::round($amount))->all(),
            'net_income_reconciliation_difference' => '0.0000',
            'reconciled' => $reconciled,
            'final_ready' => $reconciled,
        ];
    }

    /** @param array<string, mixed> $filters
     * @return array{rows: LengthAwarePaginator, total: string, display_total: string, label: string}
     */
    public function drilldown(array $filters, string $activity): array
    {
        if (! array_key_exists($activity, self::ACTIVITIES)) {
            abort(404);
        }

        $query = $activity === 'net_income'
            ? $this->netIncomeLineQuery($filters)
            : $this->equityLineQuery($filters, $activity);
        $total = $activity === 'net_income'
            ? $this->netIncome($filters)
            : $this->activityTotal($filters, $activity);

        return [
            'rows' => $query->with([
                'journalEntry:id,journal_number,journal_date,source_type,source_id,reference_number,description,status',
                'account:id,code,name,account_class,account_type,control_account_type',
            ])->orderBy('journal_entries.journal_date')->orderBy('journal_entry_lines.journal_entry_id')
                ->orderBy('journal_entry_lines.line_number')->paginate(50)->withQueryString(),
            'total' => $total,
            'display_total' => FinancialReportingConvention::round($total),
            'label' => self::ACTIVITIES[$activity],
        ];
    }

    /** @return array<string, mixed> */
    private function row(string $key, string $label, string $amount, bool $drilldown = true): array
    {
        return [
            'key' => $key,
            'label' => $label,
            'amount' => $amount,
            'display_amount' => FinancialReportingConvention::round($amount),
            'drilldown' => $drilldown,
        ];
    }

    /** @param array<string, mixed> $filters */
    private function activityTotal(array $filters, string $activity): string
    {
        $amounts = $this->equityLineQuery($filters, $activity)->reorder()->toBase()
            ->selectRaw('COALESCE(SUM(journal_entry_lines.debit), 0) AS debit')
            ->selectRaw('COALESCE(SUM(journal_entry_lines.credit), 0) AS credit')
            ->first();

        return bcsub($this->decimal($amounts->credit), $this->decimal($amounts->debit), 4);
    }

    /** @param array<string, mixed> $filters
     * @return Builder<JournalEntryLine>
     */
    private function equityLineQuery(array $filters, string $activity): Builder
    {
        $query = $this->periodLineQuery($filters)
            ->join('accounts', 'accounts.id', '=', 'journal_entry_lines.account_id')
            ->where('accounts.account_class', AccountClass::OwnerEquity);

        return match ($activity) {
            'contributions' => $query->where('accounts.account_type', AccountType::OwnerCapital),
            'drawings' => $query->where('accounts.account_type', AccountType::OwnerDrawings),
            'prior_period_adjustments' => $query
                ->where('accounts.account_type', AccountType::RetainedEarnings)
                ->where('accounts.control_account_type', 'retained_earnings'),
            default => abort(404),
        };
    }

    /** @param array<string, mixed> $filters
     * @return Builder<JournalEntryLine>
     */
    private function netIncomeLineQuery(array $filters): Builder
    {
        return $this->periodLineQuery($filters)
            ->join('accounts', 'accounts.id', '=', 'journal_entry_lines.account_id')
            ->whereIn('accounts.account_class', [
                AccountClass::Income, AccountClass::CostOfSales, AccountClass::Expense,
                AccountClass::OtherIncome, AccountClass::OtherExpense,
            ]);
    }

    /** @param array<string, mixed> $filters
     * @return Builder<JournalEntryLine>
     */
    private function periodLineQuery(array $filters): Builder
    {
        return JournalEntryLine::query()
            ->select('journal_entry_lines.*')
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_entry_lines.journal_entry_id')
            ->whereIn('journal_entries.status', FinancialReportingConvention::SOURCE_STATUSES)
            ->where('journal_entries.journal_type', '!=', JournalEntryType::Closing)
            ->whereDate('journal_entries.journal_date', '>=', $filters['start_date'])
            ->whereDate('journal_entries.journal_date', '<=', $filters['end_date']);
    }

    private function balanceSheetEquity(string $date): string
    {
        return $this->balanceSheet->generate([
            'as_of' => $date,
            'fiscal_year_start' => $this->fiscalYearStart($date),
            'fiscal_period_id' => null,
            'show_zero_balances' => false,
        ])['summary']['total_equity'];
    }

    private function fiscalYearStart(string $date): string
    {
        return (string) (FiscalYear::query()
            ->whereDate('starts_on', '<=', $date)
            ->whereDate('ends_on', '>=', $date)
            ->value('starts_on') ?? CarbonImmutable::parse($date)->startOfYear()->toDateString());
    }

    /** @param array<string, mixed> $filters */
    private function netIncome(array $filters): string
    {
        return $this->incomeStatement->generate([
            'start_date' => $filters['start_date'],
            'end_date' => $filters['end_date'],
            'as_of' => $filters['end_date'],
            'fiscal_period_id' => $filters['fiscal_period_id'] ?? null,
            'report_view' => 'period',
            'show_zero_balances' => false,
        ])['summary']['net_income_after_tax'];
    }

    private function decimal(mixed $value): string
    {
        return bcadd('0', (string) ($value ?? '0'), 4);
    }
}
