<?php

namespace App\Reports;

use App\Enums\JournalEntryStatus;
use App\Enums\JournalEntryType;
use App\Models\Account;
use App\Models\JournalEntryLine;
use App\Support\AccountingWorkflow;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class TrialBalanceReport
{
    /** @param array<string, mixed> $filters
     * @return array{rows: Collection|LengthAwarePaginator, totals: array<string, string>, balanced: bool}
     */
    public function generate(array $filters, bool $paginate = true): array
    {
        $accounts = Account::query()->ordered()->get(['id', 'code', 'name', 'parent_id', 'normal_balance', 'is_header', 'is_postable']);
        $includedIds = $this->includedAccountIds($accounts, isset($filters['account_id']) ? (int) $filters['account_id'] : null);
        $balances = $this->balances($filters, $includedIds);
        $rows = $accounts->whereIn('id', $includedIds)
            ->filter(fn (Account $account): bool => $filters['detail'] === 'hierarchy' || $account->is_postable)
            ->map(fn (Account $account): array => $this->row($account, $balances, $accounts))
            ->filter(fn (array $row): bool => $this->hasBalance($row))
            ->values();
        $totals = $this->totals($rows->where('is_header', false));

        if ($paginate) {
            $page = LengthAwarePaginator::resolveCurrentPage();
            $perPage = 50;
            $rows = new LengthAwarePaginator(
                $rows->forPage($page, $perPage)->values(),
                $rows->count(),
                $perPage,
                $page,
                ['path' => LengthAwarePaginator::resolveCurrentPath(), 'query' => request()->query()],
            );
        }

        return [
            'rows' => $rows,
            'totals' => $totals,
            'balanced' => AccountingWorkflow::isBalanced($totals['closing_debit'], $totals['closing_credit']),
        ];
    }

    /** @param array<string, mixed> $filters
     * @param  list<int>  $accountIds
     * @return Collection<int, array{opening_debit: string, opening_credit: string, movement_debit: string, movement_credit: string}>
     */
    private function balances(array $filters, array $accountIds): Collection
    {
        $query = JournalEntryLine::query()
            ->select('journal_entry_lines.account_id')
            ->selectRaw('SUM(CASE WHEN journal_entries.journal_date < ? THEN journal_entry_lines.debit ELSE 0 END) AS opening_debit', [$filters['start_date']])
            ->selectRaw('SUM(CASE WHEN journal_entries.journal_date < ? THEN journal_entry_lines.credit ELSE 0 END) AS opening_credit', [$filters['start_date']])
            ->selectRaw('SUM(CASE WHEN journal_entries.journal_date BETWEEN ? AND ? THEN journal_entry_lines.debit ELSE 0 END) AS movement_debit', [$filters['start_date'], $filters['end_date']])
            ->selectRaw('SUM(CASE WHEN journal_entries.journal_date BETWEEN ? AND ? THEN journal_entry_lines.credit ELSE 0 END) AS movement_credit', [$filters['start_date'], $filters['end_date']])
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_entry_lines.journal_entry_id')
            ->whereIn('journal_entries.status', [JournalEntryStatus::Posted, JournalEntryStatus::Reversed])
            ->whereDate('journal_entries.journal_date', '<=', $filters['as_of'])
            ->whereIn('journal_entry_lines.account_id', $accountIds)
            ->where('journal_entries.journal_type', '!=', JournalEntryType::Closing);

        if ($filters['basis'] === 'unadjusted') {
            $query->where('journal_entries.journal_type', '!=', JournalEntryType::Adjustment);
        }

        return $query->groupBy('journal_entry_lines.account_id')->get()->mapWithKeys(fn (JournalEntryLine $line): array => [
            $line->account_id => [
                'opening_debit' => $this->decimal($line->getAttribute('opening_debit')),
                'opening_credit' => $this->decimal($line->getAttribute('opening_credit')),
                'movement_debit' => $this->decimal($line->getAttribute('movement_debit')),
                'movement_credit' => $this->decimal($line->getAttribute('movement_credit')),
            ],
        ]);
    }

    /** @param Collection<int, Account> $accounts
     * @param  Collection<int, array{opening_debit: string, opening_credit: string, movement_debit: string, movement_credit: string}>  $balances
     * @return array<string, mixed>
     */
    private function row(Account $account, Collection $balances, Collection $accounts): array
    {
        $ids = $account->is_header ? $this->descendantIds($accounts, $account->id) : [$account->id];
        $openingDebit = $openingCredit = $movementDebit = $movementCredit = '0.0000';
        foreach ($ids as $id) {
            $values = $balances->get($id, []);
            $openingDebit = bcadd($openingDebit, $values['opening_debit'] ?? '0.0000', 4);
            $openingCredit = bcadd($openingCredit, $values['opening_credit'] ?? '0.0000', 4);
            $movementDebit = bcadd($movementDebit, $values['movement_debit'] ?? '0.0000', 4);
            $movementCredit = bcadd($movementCredit, $values['movement_credit'] ?? '0.0000', 4);
        }

        [$openingDebitBalance, $openingCreditBalance] = $this->presentation($openingDebit, $openingCredit);
        [$closingDebit, $closingCredit] = $this->presentation(
            bcadd($openingDebit, $movementDebit, 4),
            bcadd($openingCredit, $movementCredit, 4),
        );

        return [
            'account' => $account,
            'is_header' => $account->is_header,
            'opening_debit' => $openingDebitBalance,
            'opening_credit' => $openingCreditBalance,
            'movement_debit' => $movementDebit,
            'movement_credit' => $movementCredit,
            'closing_debit' => $closingDebit,
            'closing_credit' => $closingCredit,
        ];
    }

    /** @param Collection<int, array<string, mixed>> $rows
     * @return array<string, string>
     */
    private function totals(Collection $rows): array
    {
        $keys = ['opening_debit', 'opening_credit', 'movement_debit', 'movement_credit', 'closing_debit', 'closing_credit'];

        return collect($keys)->mapWithKeys(fn (string $key): array => [
            $key => $rows->reduce(fn (string $total, array $row): string => bcadd($total, $row[$key], 4), '0.0000'),
        ])->all();
    }

    /** @return array{string, string} */
    private function presentation(string $debit, string $credit): array
    {
        $net = bcsub($debit, $credit, 4);

        return bccomp($net, '0', 4) >= 0
            ? [$net, '0.0000']
            : ['0.0000', bcmul($net, '-1', 4)];
    }

    /** @param Collection<int, Account> $accounts
     * @return list<int>
     */
    private function includedAccountIds(Collection $accounts, ?int $accountId): array
    {
        return $accountId === null ? $accounts->pluck('id')->all() : $this->descendantIds($accounts, $accountId, true);
    }

    /** @param Collection<int, Account> $accounts
     * @return list<int>
     */
    private function descendantIds(Collection $accounts, int $parentId, bool $includeParent = false): array
    {
        $ids = $includeParent ? [$parentId] : [];
        $parents = [$parentId];
        do {
            $children = $accounts->whereIn('parent_id', $parents)->pluck('id')->all();
            $ids = [...$ids, ...$children];
            $parents = $children;
        } while ($children !== []);

        return $ids;
    }

    /** @param array<string, mixed> $row */
    private function hasBalance(array $row): bool
    {
        foreach (['opening_debit', 'opening_credit', 'movement_debit', 'movement_credit', 'closing_debit', 'closing_credit'] as $key) {
            if (bccomp($row[$key], '0', 4) !== 0) {
                return true;
            }
        }

        return false;
    }

    private function decimal(mixed $value): string
    {
        return bcadd('0', (string) ($value ?? '0'), 4);
    }
}
