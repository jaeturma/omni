<?php

namespace App\Reports;

use App\Enums\JournalEntryStatus;
use App\Enums\NormalBalance;
use App\Models\Account;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use App\Support\AccountingWorkflow;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class GeneralLedgerReport
{
    /** @param array<string, mixed> $filters */
    public function journal(array $filters, bool $paginate = true): LengthAwarePaginator|Collection
    {
        $query = JournalEntry::query()
            ->select(['id', 'journal_number', 'journal_date', 'journal_type', 'source_type', 'source_id',
                'reference_number', 'description', 'total_debit', 'total_credit', 'status', 'reverses_entry_id'])
            ->whereIn('status', [JournalEntryStatus::Posted, JournalEntryStatus::Reversed])
            ->whereBetween('journal_date', [$filters['start_date'], $filters['end_date']])
            ->with('postedBy:id,name')
            ->withCount('lines')
            ->when($filters['source_type'] ?? null, fn (Builder $query, string $value) => $query->where('source_type', $value))
            ->when($filters['reference'] ?? null, fn (Builder $query, string $value) => $query->where(function (Builder $query) use ($value): void {
                $query->where('reference_number', 'like', "%{$value}%")->orWhere('journal_number', 'like', "%{$value}%");
            }))
            ->orderBy('journal_date')->orderBy('id');

        return $paginate ? $query->paginate(50)->withQueryString() : $query->cursor()->collect();
    }

    /** @param array<string, mixed> $filters
     * @return array{rows: LengthAwarePaginator|Collection, opening: string, debit: string, credit: string, closing: string}
     */
    public function ledger(array $filters, bool $paginate = true): array
    {
        $account = isset($filters['account_id']) ? Account::find($filters['account_id']) : null;
        $accountIds = $account ? $this->accountIds($account, (bool) ($filters['include_descendants'] ?? false)) : [];
        $query = $this->lineQuery($filters, $accountIds);
        $opening = $this->openingBalance($filters, $accountIds, $account);
        $totals = $this->totals(clone $query);

        if (! $paginate) {
            $rows = $query->cursor()->collect();
            $this->attachRunningBalances($rows, $opening, $account);

            return ['rows' => $rows, 'opening' => $opening, ...$totals,
                'closing' => $this->movement($opening, $totals['debit'], $totals['credit'], $account)];
        }

        $rows = $query->paginate(50)->withQueryString();
        $prior = (clone $query)->limit($rows->firstItem() ? $rows->firstItem() - 1 : 0)->cursor()->collect();
        $pageOpening = $this->attachRunningBalances($prior, $opening, $account);
        $this->attachRunningBalances($rows->getCollection(), $pageOpening, $account);

        return ['rows' => $rows, 'opening' => $opening, ...$totals,
            'closing' => $this->movement($opening, $totals['debit'], $totals['credit'], $account)];
    }

    /** @param array<string, mixed> $filters
     * @param  list<int>  $accountIds
     * @return Builder<JournalEntryLine>
     */
    private function lineQuery(array $filters, array $accountIds): Builder
    {
        return JournalEntryLine::query()
            ->select('journal_entry_lines.*')
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_entry_lines.journal_entry_id')
            ->whereIn('journal_entries.status', [JournalEntryStatus::Posted, JournalEntryStatus::Reversed])
            ->whereBetween('journal_entries.journal_date', [$filters['start_date'], $filters['end_date']])
            ->when($accountIds !== [], fn (Builder $query) => $query->whereIn('journal_entry_lines.account_id', $accountIds))
            ->when($filters['source_type'] ?? null, fn (Builder $query, string $value) => $query->where('journal_entries.source_type', $value))
            ->when($filters['reference'] ?? null, fn (Builder $query, string $value) => $query->where(function (Builder $query) use ($value): void {
                $query->where('journal_entries.reference_number', 'like', "%{$value}%")
                    ->orWhere('journal_entries.journal_number', 'like', "%{$value}%");
            }))
            ->when($filters['customer_id'] ?? null, fn (Builder $query, int $value) => $query->where('journal_entry_lines.customer_id', $value))
            ->when($filters['supplier_id'] ?? null, fn (Builder $query, int $value) => $query->where('journal_entry_lines.supplier_id', $value))
            ->when($filters['financial_account_id'] ?? null, fn (Builder $query, int $value) => $query->where('journal_entry_lines.financial_account_id', $value))
            ->when($filters['product_id'] ?? null, fn (Builder $query, int $value) => $query->where('journal_entry_lines.product_id', $value))
            ->when($filters['warehouse_id'] ?? null, fn (Builder $query, int $value) => $query->where('journal_entry_lines.warehouse_id', $value))
            ->with([
                'account:id,code,name,normal_balance', 'journalEntry:id,journal_number,journal_date,source_type,source_id,reference_number,description,status,reverses_entry_id',
                'customer:id,code,name', 'supplier:id,code,name', 'financialAccount:id,code,name',
                'warehouse:id,code,name', 'product:id,sku,name',
            ])
            ->orderBy('journal_entries.journal_date')
            ->orderBy('journal_entry_lines.journal_entry_id')
            ->orderBy('journal_entry_lines.line_number');
    }

    /** @param array<string, mixed> $filters
     * @param  list<int>  $accountIds
     */
    private function openingBalance(array $filters, array $accountIds, ?Account $account): string
    {
        $openingFilters = $filters;
        $openingFilters['start_date'] = '1900-01-01';
        $openingFilters['end_date'] = $filters['start_date'];
        $query = $this->lineQuery($openingFilters, $accountIds)
            ->where('journal_entries.journal_date', '<', $filters['start_date']);
        $totals = $this->totals($query);

        return $this->movement('0.0000', $totals['debit'], $totals['credit'], $account);
    }

    /** @param Builder<JournalEntryLine> $query
     * @return array{debit: string, credit: string}
     */
    private function totals(Builder $query): array
    {
        $row = $query->reorder()->toBase()->selectRaw(
            'COALESCE(SUM(journal_entry_lines.debit), 0) AS debit, COALESCE(SUM(journal_entry_lines.credit), 0) AS credit'
        )->first();

        return ['debit' => number_format((float) $row->debit, AccountingWorkflow::AMOUNT_SCALE, '.', ''),
            'credit' => number_format((float) $row->credit, AccountingWorkflow::AMOUNT_SCALE, '.', '')];
    }

    /** @param Collection<int, JournalEntryLine> $rows */
    private function attachRunningBalances(Collection $rows, string $opening, ?Account $selectedAccount): string
    {
        $running = $opening;
        foreach ($rows as $row) {
            $account = $selectedAccount ?? $row->account;
            if (! $account instanceof Account) {
                throw new \LogicException('Every journal line must belong to an account.');
            }
            $running = $this->movement($running, $row->debit, $row->credit, $account);
            $row->setAttribute('running_balance', $running);
        }

        return $running;
    }

    private function movement(string $opening, string $debit, string $credit, ?Account $account): string
    {
        $normal = $account instanceof Account ? $account->normal_balance : NormalBalance::Debit;
        $change = $normal === NormalBalance::Credit
            ? bcsub($credit, $debit, AccountingWorkflow::AMOUNT_SCALE)
            : bcsub($debit, $credit, AccountingWorkflow::AMOUNT_SCALE);

        return bcadd($opening, $change, AccountingWorkflow::AMOUNT_SCALE);
    }

    /** @return list<int> */
    private function accountIds(Account $account, bool $includeDescendants): array
    {
        $ids = [$account->id];
        if (! $includeDescendants) {
            return $ids;
        }

        $parents = Account::query()->pluck('parent_id', 'id');
        do {
            $children = $parents->filter(fn (?int $parentId) => in_array($parentId, $ids, true))->keys()->all();
            $newIds = array_values(array_diff($children, $ids));
            $ids = [...$ids, ...$newIds];
        } while ($newIds !== []);

        return $ids;
    }
}
