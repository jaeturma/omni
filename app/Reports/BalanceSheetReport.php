<?php

namespace App\Reports;

use App\Enums\AccountClass;
use App\Enums\AccountType;
use App\Enums\CurrentClassification;
use App\Enums\JournalEntryType;
use App\Enums\NormalBalance;
use App\Models\Account;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use App\Support\AccountingWorkflow;
use App\Support\FinancialReportingConvention;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class BalanceSheetReport
{
    private const SECTIONS = [
        'current_assets' => 'Current Assets',
        'non_current_assets' => 'Non-current Assets',
        'current_liabilities' => 'Current Liabilities',
        'non_current_liabilities' => 'Non-current Liabilities',
        'owner_capital' => "Owner's Capital",
        'owner_drawings' => "Owner's Drawings",
        'prior_year_equity' => 'Prior-year Equity',
        'current_year_earnings' => 'Current-year Earnings',
    ];

    public function __construct(private IncomeStatementReport $incomeStatement) {}

    /** @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    public function generate(array $filters): array
    {
        $accounts = $this->accounts();
        $balances = $this->balances($filters, $accounts);
        [$currentYearEarnings, $derived] = $this->currentYearEarnings($filters, $accounts, $balances);
        if ($derived) {
            $currentYearAccounts = $accounts->filter(fn (Account $account): bool => $this->sectionFor($account) === 'current_year_earnings');
            foreach ($currentYearAccounts as $account) {
                $balances->put($account->id, $account->id === $currentYearAccounts->first()?->id ? $currentYearEarnings : '0.0000');
            }
        }

        $sections = collect(self::SECTIONS)->map(function (string $label, string $key) use ($accounts, $balances, $filters): array {
            $sectionAccounts = $accounts->filter(fn (Account $account): bool => $this->sectionFor($account) === $key);
            $rows = $sectionAccounts->map(fn (Account $account): array => $this->row($account, $key, $accounts, $balances))
                ->filter(fn (array $row): bool => (bool) $filters['show_zero_balances'] || bccomp($row['amount'], '0', 4) !== 0)
                ->values();
            $total = $sectionAccounts->where('is_postable', true)
                ->reduce(fn (string $total, Account $account): string => bcadd($total, $balances->get($account->id, '0.0000'), 4), '0.0000');

            return [
                'key' => $key,
                'label' => $label,
                'rows' => $rows,
                'total' => $total,
                'display_total' => FinancialReportingConvention::round($total),
            ];
        });

        $totals = $sections->mapWithKeys(fn (array $section): array => [$section['key'] => $section['total']]);
        $summary = $this->summary($totals);
        $balanced = AccountingWorkflow::isBalanced($summary['total_assets'], $summary['liabilities_and_equity']);

        return [
            'sections' => $sections,
            'summary' => $summary,
            'display_summary' => collect($summary)->map(fn (string $amount): string => FinancialReportingConvention::round($amount))->all(),
            'balanced' => $balanced,
            'final_ready' => $balanced,
            'current_year_earnings_derived' => $derived,
        ];
    }

    /** @param array<string, mixed> $filters
     * @return array{rows: LengthAwarePaginator, total: string, display_total: string, derived: bool}
     */
    public function drilldown(array $filters, Account $account): array
    {
        $accounts = $this->accounts();
        $section = $this->sectionFor($account);
        if ($section === null) {
            abort(404);
        }

        $derived = $section === 'current_year_earnings' && ! $this->hasFormalClosing($filters);
        if ($derived) {
            $query = $this->incomeLineQuery($filters);
            $total = $this->incomeStatement->generate([
                'start_date' => $filters['fiscal_year_start'],
                'end_date' => $filters['as_of'],
                'as_of' => $filters['as_of'],
                'fiscal_period_id' => $filters['fiscal_period_id'] ?? null,
                'report_view' => 'year_to_date',
                'show_zero_balances' => false,
            ])['summary']['net_income_after_tax'];
        } else {
            $accountIds = $this->descendantIds($accounts, $account->id)
                ->filter(fn (int $id): bool => $this->sectionFor($accounts->firstWhere('id', $id)) === $section)
                ->values()->all();
            $query = $this->lineQuery($filters)->whereIn('journal_entry_lines.account_id', $accountIds);
            $amounts = (clone $query)->reorder()->toBase()
                ->selectRaw('COALESCE(SUM(journal_entry_lines.debit), 0) AS debit, COALESCE(SUM(journal_entry_lines.credit), 0) AS credit')
                ->first();
            $total = $this->statementAmount(
                $this->decimal($amounts->debit),
                $this->decimal($amounts->credit),
                $account->account_class,
            );
        }

        return [
            'rows' => $query->with([
                'journalEntry:id,journal_number,journal_date,source_type,source_id,reference_number,description,status',
                'account:id,code,name,normal_balance',
            ])->orderBy('journal_entries.journal_date')->orderBy('journal_entry_lines.journal_entry_id')
                ->orderBy('journal_entry_lines.line_number')->paginate(50)->withQueryString(),
            'total' => $total,
            'display_total' => FinancialReportingConvention::round($total),
            'derived' => $derived,
        ];
    }

    /** @return Collection<int, Account> */
    private function accounts(): Collection
    {
        return Account::query()
            ->whereIn('account_class', [AccountClass::Asset, AccountClass::Liability, AccountClass::OwnerEquity])
            ->ordered()
            ->get([
                'id', 'code', 'name', 'account_class', 'account_type', 'normal_balance',
                'current_classification', 'control_account_type', 'parent_id', 'is_header', 'is_postable',
            ]);
    }

    /** @param array<string, mixed> $filters
     * @param  Collection<int, Account>  $accounts
     * @return Collection<int, string>
     */
    private function balances(array $filters, Collection $accounts): Collection
    {
        return $this->lineQuery($filters)
            ->whereIn('journal_entry_lines.account_id', $accounts->pluck('id'))
            ->reorder()
            ->select('journal_entry_lines.account_id')
            ->selectRaw('COALESCE(SUM(journal_entry_lines.debit), 0) AS debit')
            ->selectRaw('COALESCE(SUM(journal_entry_lines.credit), 0) AS credit')
            ->groupBy('journal_entry_lines.account_id')
            ->get()
            ->mapWithKeys(function (JournalEntryLine $line) use ($accounts): array {
                $account = $accounts->firstWhere('id', $line->account_id);

                return [$line->account_id => $this->statementAmount(
                    $this->decimal($line->getAttribute('debit')),
                    $this->decimal($line->getAttribute('credit')),
                    $account->account_class,
                )];
            });
    }

    /** @param array<string, mixed> $filters
     * @return Builder<JournalEntryLine>
     */
    private function lineQuery(array $filters): Builder
    {
        return JournalEntryLine::query()
            ->select('journal_entry_lines.*')
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_entry_lines.journal_entry_id')
            ->whereIn('journal_entries.status', FinancialReportingConvention::SOURCE_STATUSES)
            ->whereDate('journal_entries.journal_date', '<=', $filters['as_of']);
    }

    /** @param array<string, mixed> $filters
     * @return Builder<JournalEntryLine>
     */
    private function incomeLineQuery(array $filters): Builder
    {
        return JournalEntryLine::query()
            ->select('journal_entry_lines.*')
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_entry_lines.journal_entry_id')
            ->join('accounts', 'accounts.id', '=', 'journal_entry_lines.account_id')
            ->whereIn('journal_entries.status', FinancialReportingConvention::SOURCE_STATUSES)
            ->where('journal_entries.journal_type', '!=', JournalEntryType::Closing)
            ->whereBetween('journal_entries.journal_date', [$filters['fiscal_year_start'], $filters['as_of']])
            ->whereIn('accounts.account_class', [
                AccountClass::Income, AccountClass::CostOfSales, AccountClass::Expense,
                AccountClass::OtherIncome, AccountClass::OtherExpense,
            ]);
    }

    /** @param Collection<int, Account> $accounts
     * @param  Collection<int, string>  $balances
     * @return array{string, bool}
     */
    private function currentYearEarnings(array $filters, Collection $accounts, Collection $balances): array
    {
        if ($this->hasFormalClosing($filters)) {
            $amount = $accounts->filter(fn (Account $account): bool => $this->sectionFor($account) === 'current_year_earnings')
                ->where('is_postable', true)
                ->reduce(fn (string $total, Account $account): string => bcadd($total, $balances->get($account->id, '0.0000'), 4), '0.0000');

            return [$amount, false];
        }

        $income = $this->incomeStatement->generate([
            'start_date' => $filters['fiscal_year_start'],
            'end_date' => $filters['as_of'],
            'as_of' => $filters['as_of'],
            'fiscal_period_id' => $filters['fiscal_period_id'] ?? null,
            'report_view' => 'year_to_date',
            'show_zero_balances' => false,
        ]);

        return [$income['summary']['net_income_after_tax'], true];
    }

    private function hasFormalClosing(array $filters): bool
    {
        return JournalEntry::query()
            ->whereIn('status', array_map(
                static fn ($status): string => $status->value,
                FinancialReportingConvention::SOURCE_STATUSES,
            ))
            ->where('journal_type', JournalEntryType::Closing->value)
            ->whereDate('journal_date', '>=', $filters['fiscal_year_start'])
            ->whereDate('journal_date', '<=', $filters['as_of'])
            ->whereHas('lines.account', fn (Builder $query): Builder => $query
                ->where('account_type', AccountType::RetainedEarnings->value)
                ->where('control_account_type', 'current_year_earnings'))
            ->exists();
    }

    /** @param Collection<int, Account> $accounts
     * @param  Collection<int, string>  $balances
     * @return array<string, mixed>
     */
    private function row(Account $account, string $section, Collection $accounts, Collection $balances): array
    {
        $amount = $this->descendantIds($accounts, $account->id)
            ->filter(fn (int $id): bool => $this->sectionFor($accounts->firstWhere('id', $id)) === $section)
            ->reduce(fn (string $total, int $id): string => bcadd($total, $balances->get($id, '0.0000'), 4), '0.0000');

        return [
            'account' => $account,
            'amount' => $amount,
            'display_amount' => FinancialReportingConvention::round($amount),
            'depth' => $this->depth($accounts, $account, $section),
        ];
    }

    private function sectionFor(?Account $account): ?string
    {
        if ($account === null) {
            return null;
        }

        return match (true) {
            $account->account_class === AccountClass::Asset && $account->current_classification === CurrentClassification::Current => 'current_assets',
            $account->account_class === AccountClass::Asset => 'non_current_assets',
            $account->account_class === AccountClass::Liability && $account->current_classification === CurrentClassification::Current => 'current_liabilities',
            $account->account_class === AccountClass::Liability => 'non_current_liabilities',
            $account->account_type === AccountType::OwnerDrawings => 'owner_drawings',
            $account->account_type === AccountType::RetainedEarnings && $account->control_account_type === 'current_year_earnings' => 'current_year_earnings',
            $account->account_type === AccountType::RetainedEarnings => 'prior_year_equity',
            $account->account_class === AccountClass::OwnerEquity => 'owner_capital',
            default => null,
        };
    }

    private function statementAmount(string $debit, string $credit, AccountClass $accountClass): string
    {
        return FinancialReportingConvention::present(
            $debit,
            $credit,
            $accountClass === AccountClass::Asset ? NormalBalance::Debit : NormalBalance::Credit,
        );
    }

    /** @param Collection<int, Account> $accounts
     * @return Collection<int, int>
     */
    private function descendantIds(Collection $accounts, int $parentId): Collection
    {
        $ids = collect([$parentId]);
        $parents = [$parentId];
        do {
            $children = $accounts->whereIn('parent_id', $parents)->pluck('id')->all();
            $ids = $ids->concat($children);
            $parents = $children;
        } while ($children !== []);

        return $ids;
    }

    /** @param Collection<int, Account> $accounts */
    private function depth(Collection $accounts, Account $account, string $section): int
    {
        $depth = 0;
        $parentId = $account->parent_id;
        while ($parentId !== null && $accounts->contains('id', $parentId)) {
            $parent = $accounts->firstWhere('id', $parentId);
            if ($this->sectionFor($parent) !== $section) {
                break;
            }
            $depth++;
            $parentId = $parent?->parent_id;
        }

        return $depth;
    }

    /** @param Collection<string, string> $totals
     * @return array<string, string>
     */
    private function summary(Collection $totals): array
    {
        $totalAssets = bcadd($totals['current_assets'], $totals['non_current_assets'], 4);
        $totalLiabilities = bcadd($totals['current_liabilities'], $totals['non_current_liabilities'], 4);
        $totalEquity = bcadd(
            bcadd($totals['owner_capital'], $totals['owner_drawings'], 4),
            bcadd($totals['prior_year_equity'], $totals['current_year_earnings'], 4),
            4,
        );
        $liabilitiesAndEquity = bcadd($totalLiabilities, $totalEquity, 4);

        return [
            ...$totals->all(),
            'total_assets' => $totalAssets,
            'total_liabilities' => $totalLiabilities,
            'total_equity' => $totalEquity,
            'liabilities_and_equity' => $liabilitiesAndEquity,
            'difference' => bcsub($totalAssets, $liabilitiesAndEquity, 4),
        ];
    }

    private function decimal(mixed $value): string
    {
        return bcadd('0', (string) ($value ?? '0'), 4);
    }
}
