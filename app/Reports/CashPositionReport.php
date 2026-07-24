<?php

namespace App\Reports;

use App\Enums\CashTransactionStatus;
use App\Enums\CashTransactionType;
use App\Enums\FundTransferStatus;
use App\Enums\ReconciliationState;
use App\Models\BankReconciliation;
use App\Models\BankStatementLine;
use App\Models\CashTransaction;
use App\Models\FinancialAccount;
use App\Models\FundTransfer;
use App\Services\BankReconciliationCalculator;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class CashPositionReport
{
    public function __construct(private BankReconciliationCalculator $calculator) {}

    public function signed(CashTransaction $transaction): string
    {
        return $this->calculator->signed($transaction);
    }

    /** @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    public function summary(array $filters, bool $includeReconciliation): array
    {
        $accounts = $this->accounts($filters);
        $positions = $accounts->mapWithKeys(function (FinancialAccount $account) use ($filters): array {
            $effectiveDate = $account->opening_balance_date;
            $opening = $effectiveDate === null || $effectiveDate->lt($filters['start_date']) ? $account->opening_balance : '0.0000';
            $movement = $effectiveDate?->betweenIncluded($filters['start_date'], $filters['end_date']) ? $account->opening_balance : '0.0000';
            $asOf = $effectiveDate === null || $effectiveDate->lte($filters['as_of']) ? $account->opening_balance : '0.0000';

            return [$account->id => ['account' => $account, 'opening' => $opening, 'movement' => $movement,
                'closing' => bcadd($opening, $movement, 4), 'as_of' => $asOf]];
        })->all();
        $receipts = $disbursements = $reconciled = $unreconciled = '0.0000';
        $sourceMovements = [];
        $dailyMovements = [];
        foreach ($this->postedQuery($filters, false, false)->cursor() as $transaction) {
            $signed = $this->calculator->signed($transaction);
            $position = $positions[$transaction->financial_account_id];
            if ($transaction->transaction_date->lt($filters['start_date'])) {
                $position['opening'] = bcadd($position['opening'], $signed, 4);
            }
            if ($transaction->transaction_date->betweenIncluded($filters['start_date'], $filters['end_date'])) {
                $position['movement'] = bcadd($position['movement'], $signed, 4);
            }
            if ($transaction->transaction_date->lte($filters['as_of'])) {
                $position['as_of'] = bcadd($position['as_of'], $signed, 4);
            }
            $positions[$transaction->financial_account_id] = $position;

            if ($transaction->transaction_date->betweenIncluded($filters['start_date'], $filters['end_date'])
                && (blank($filters['transaction_type']) || $transaction->type->value === $filters['transaction_type'])) {
                $date = $transaction->transaction_date->toDateString();
                $dailyMovements[$date] ??= ['receipts' => '0.0000', 'disbursements' => '0.0000'];
                if (bccomp($signed, '0', 4) >= 0) {
                    $receipts = bcadd($receipts, $signed, 4);
                    $dailyMovements[$date]['receipts'] = bcadd($dailyMovements[$date]['receipts'], $signed, 4);
                } else {
                    $outflow = bcmul($signed, '-1', 4);
                    $disbursements = bcadd($disbursements, $outflow, 4);
                    $dailyMovements[$date]['disbursements'] = bcadd($dailyMovements[$date]['disbursements'], $outflow, 4);
                }
                $sourceMovements[$transaction->type->value] = bcadd($sourceMovements[$transaction->type->value] ?? '0.0000', $signed, 4);
                if ($transaction->finalized_reconciliation_match_exists) {
                    $reconciled = bcadd($reconciled, str_replace('-', '', $signed), 4);
                } else {
                    $unreconciled = bcadd($unreconciled, str_replace('-', '', $signed), 4);
                }
            }
        }
        foreach ($positions as &$position) {
            $position['closing'] = bcadd($position['opening'], $position['movement'], 4);
        }

        return [
            'positions' => collect($positions), 'receipts' => $receipts, 'disbursements' => $disbursements,
            'net_movement' => bcsub($receipts, $disbursements, 4), 'reconciled' => $reconciled, 'unreconciled' => $unreconciled,
            'source_movements' => $sourceMovements, 'daily_movements' => $dailyMovements,
            'transfers_in_transit' => FundTransfer::query()->with(['sourceFinancialAccount:id,code', 'destinationFinancialAccount:id,code'])
                ->where('status', FundTransferStatus::Posted)->where(fn (Builder $query) => $query->whereIn('source_financial_account_id', array_keys($positions))
                ->orWhereIn('destination_financial_account_id', array_keys($positions)))
                ->whereBetween('transfer_date', [$filters['start_date'], $filters['as_of']])->latest('transfer_date')->limit(25)->get(),
            'petty_cash_activity' => $this->postedQuery($filters)->whereIn('type', [
                CashTransactionType::PettyCashRelease, CashTransactionType::PettyCashReturn, CashTransactionType::PettyCashReplenishment,
            ])->latest('transaction_date')->limit(25)->get(),
            'unreconciled_items' => $includeReconciliation ? $this->unreconciledItems($filters) : collect(),
            'reconciliation_history' => $includeReconciliation ? $this->reconciliationHistory($filters) : collect(),
        ];
    }

    /** @param array<string, mixed> $filters */
    public function activityPaginator(array $filters): LengthAwarePaginator
    {
        return $this->postedQuery($filters)->with(['financialAccount:id,code,name'])->latest('transaction_date')->latest('id')->paginate(50)->withQueryString();
    }

    /** @param array<string, mixed> $filters
     * @return Builder<CashTransaction>
     */
    public function postedQuery(array $filters, bool $rangeOnly = true, bool $applyType = true): Builder
    {
        return CashTransaction::query()->withExists('finalizedReconciliationMatch')->where('status', CashTransactionStatus::Posted)
            ->whereIn('financial_account_id', $this->accounts($filters)->pluck('id'))
            ->when($rangeOnly, fn (Builder $query) => $query->whereBetween('transaction_date', [$filters['start_date'], $filters['end_date']]))
            ->when(! $rangeOnly, fn (Builder $query) => $query->whereDate('transaction_date', '<=', $filters['as_of']))
            ->when($applyType && filled($filters['transaction_type']), fn (Builder $query) => $query->where('type', $filters['transaction_type']));
    }

    /** @param array<string, mixed> $filters
     * @return Collection<int, FinancialAccount>
     */
    private function accounts(array $filters): Collection
    {
        return FinancialAccount::query()->when(filled($filters['financial_account_id']), fn (Builder $query) => $query->whereKey($filters['financial_account_id']))
            ->when(filled($filters['account_type']), fn (Builder $query) => $query->where('type', $filters['account_type']))->orderBy('name')->get();
    }

    /** @param array<string, mixed> $filters */
    private function unreconciledItems(array $filters): Collection
    {
        return BankStatementLine::query()->with('bankStatementImport.financialAccount:id,code,name')
            ->whereIn('match_status', [ReconciliationState::Unreconciled, ReconciliationState::Disputed])
            ->whereBetween('transaction_date', [$filters['start_date'], $filters['as_of']])
            ->whereHas('bankStatementImport', fn (Builder $query) => $query->whereIn('financial_account_id', $this->accounts($filters)->pluck('id')))
            ->latest('transaction_date')->limit(50)->get();
    }

    /** @param array<string, mixed> $filters */
    private function reconciliationHistory(array $filters): Collection
    {
        return BankReconciliation::query()->with('financialAccount:id,code,name')->whereIn('financial_account_id', $this->accounts($filters)->pluck('id'))
            ->whereDate('statement_end_date', '>=', $filters['start_date'])->whereDate('statement_end_date', '<=', $filters['as_of'])
            ->latest('statement_end_date')->limit(25)->get();
    }
}
