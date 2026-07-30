<?php

namespace App\Reports;

use App\Enums\AccountClass;
use App\Enums\AccountType;
use App\Enums\JournalEntryType;
use App\Models\Account;
use App\Models\JournalEntryLine;
use App\Support\FinancialReportingConvention;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class IncomeStatementReport
{
    private const SECTIONS = [
        'revenue' => 'Revenue',
        'contra_revenue' => 'Sales Returns and Discounts',
        'cost_of_sales' => 'Cost of Sales',
        'operating_expenses' => 'Operating Expenses',
        'other_income' => 'Other Income',
        'other_expenses' => 'Other Expenses',
        'income_tax' => 'Income Tax Expense',
    ];

    /** @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    public function generate(array $filters): array
    {
        $accounts = $this->accounts();
        $balances = $this->balances($filters, $accounts);
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

        return [
            'sections' => $sections,
            'summary' => $summary,
            'display_summary' => collect($summary)->map(fn (string $amount): string => FinancialReportingConvention::round($amount))->all(),
            'has_income_tax' => bccomp($summary['income_tax'], '0', 4) !== 0,
            'reconciliation_difference' => bcsub($summary['net_income_after_tax'], $this->netActivity($totals), 4),
        ];
    }

    /** @param array<string, mixed> $filters
     * @return array{rows: LengthAwarePaginator, total: string, display_total: string}
     */
    public function drilldown(array $filters, Account $account): array
    {
        $accounts = $this->accounts();
        $section = $this->sectionFor($account);
        if ($section === null) {
            abort(404);
        }

        $accountIds = $this->descendantIds($accounts, $account->id)
            ->filter(fn (int $id): bool => $this->sectionFor($accounts->firstWhere('id', $id)) === $section)
            ->values()->all();
        $query = $this->lineQuery($filters)->whereIn('journal_entry_lines.account_id', $accountIds);
        $amounts = (clone $query)->reorder()->toBase()
            ->selectRaw('COALESCE(SUM(journal_entry_lines.debit), 0) AS debit, COALESCE(SUM(journal_entry_lines.credit), 0) AS credit')
            ->first();
        $total = FinancialReportingConvention::present(
            $this->decimal($amounts->debit),
            $this->decimal($amounts->credit),
            $account->normal_balance,
        );

        return [
            'rows' => $query->with([
                'journalEntry:id,journal_number,journal_date,source_type,source_id,reference_number,description,status',
                'account:id,code,name,normal_balance',
            ])->orderBy('journal_entries.journal_date')->orderBy('journal_entry_lines.journal_entry_id')
                ->orderBy('journal_entry_lines.line_number')->paginate(50)->withQueryString(),
            'total' => $total,
            'display_total' => FinancialReportingConvention::round($total),
        ];
    }

    /** @return Collection<int, Account> */
    private function accounts(): Collection
    {
        return Account::query()
            ->whereIn('account_class', [
                AccountClass::Income, AccountClass::CostOfSales, AccountClass::Expense,
                AccountClass::OtherIncome, AccountClass::OtherExpense,
            ])
            ->ordered()
            ->get(['id', 'code', 'name', 'account_class', 'account_type', 'normal_balance', 'parent_id', 'is_header', 'is_postable']);
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

                return [$line->account_id => FinancialReportingConvention::present(
                    $this->decimal($line->getAttribute('debit')),
                    $this->decimal($line->getAttribute('credit')),
                    $account->normal_balance,
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
            ->where('journal_entries.journal_type', '!=', JournalEntryType::Closing)
            ->whereBetween('journal_entries.journal_date', [$filters['start_date'], $filters['end_date']]);
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
            $account->account_type === AccountType::SalesReturnsDiscounts => 'contra_revenue',
            $account->account_type === AccountType::IncomeTaxExpense => 'income_tax',
            $account->account_class === AccountClass::Income => 'revenue',
            $account->account_class === AccountClass::CostOfSales => 'cost_of_sales',
            $account->account_class === AccountClass::Expense => 'operating_expenses',
            $account->account_class === AccountClass::OtherIncome => 'other_income',
            $account->account_class === AccountClass::OtherExpense => 'other_expenses',
            default => null,
        };
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
        $netSales = bcsub($totals['revenue'], $totals['contra_revenue'], 4);
        $grossProfit = bcsub($netSales, $totals['cost_of_sales'], 4);
        $operatingIncome = bcsub($grossProfit, $totals['operating_expenses'], 4);
        $netIncomeBeforeTax = bcsub(
            bcadd($operatingIncome, $totals['other_income'], 4),
            $totals['other_expenses'],
            4,
        );

        return [
            ...$totals->all(),
            'net_sales' => $netSales,
            'gross_profit' => $grossProfit,
            'operating_income' => $operatingIncome,
            'net_income_before_tax' => $netIncomeBeforeTax,
            'net_income_after_tax' => bcsub($netIncomeBeforeTax, $totals['income_tax'], 4),
        ];
    }

    /** @param Collection<string, string> $totals */
    private function netActivity(Collection $totals): string
    {
        return bcsub(
            bcadd($totals['revenue'], $totals['other_income'], 4),
            bcadd(
                bcadd($totals['contra_revenue'], $totals['cost_of_sales'], 4),
                bcadd($totals['operating_expenses'], bcadd($totals['other_expenses'], $totals['income_tax'], 4), 4),
                4,
            ),
            4,
        );
    }

    private function decimal(mixed $value): string
    {
        return bcadd('0', (string) ($value ?? '0'), 4);
    }
}
