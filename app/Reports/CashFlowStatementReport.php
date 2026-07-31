<?php

namespace App\Reports;

use App\Enums\AccountClass;
use App\Enums\AccountType;
use App\Enums\CashFlowClassification;
use App\Enums\JournalEntryType;
use App\Models\Account;
use App\Models\JournalEntryLine;
use App\Support\AccountingWorkflow;
use App\Support\FinancialReportingConvention;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class CashFlowStatementReport
{
    private const MATERIALITY = '0.0050';

    public function __construct(private IncomeStatementReport $incomeStatement) {}

    /** @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    public function generate(array $filters): array
    {
        $accounts = $this->accounts();
        $activity = $this->activity($filters, $accounts);
        $netIncome = $this->netIncome($filters);
        $sections = collect([
            'operating' => $this->operatingRows($accounts, $activity, $netIncome),
            'investing' => $this->classifiedRows($accounts, $activity, CashFlowClassification::Investing),
            'financing' => $this->classifiedRows($accounts, $activity, CashFlowClassification::Financing),
            'unclassified' => $this->unclassifiedRows($accounts, $activity),
        ])->map(function (Collection $rows, string $key): array {
            $total = $rows->reduce(
                fn (string $sum, array $row): string => bcadd($sum, $row['amount'], 4),
                '0.0000',
            );

            return [
                'key' => $key,
                'label' => match ($key) {
                    'operating' => 'Operating Activities',
                    'investing' => 'Investing Activities',
                    'financing' => 'Financing Activities',
                    default => 'Unclassified Activity',
                },
                'rows' => $rows->values(),
                'total' => $total,
                'display_total' => FinancialReportingConvention::round($total),
            ];
        });

        $beginningCash = $this->cashBalance($filters['start_date'], false);
        $endingCash = $this->cashBalance($filters['end_date'], true);
        $netChange = $sections->reduce(
            fn (string $sum, array $section): string => bcadd($sum, $section['total'], 4),
            '0.0000',
        );
        $calculatedEnding = bcadd($beginningCash, $netChange, 4);
        $difference = bcsub($calculatedEnding, $endingCash, 4);
        $hasUnclassified = $sections['unclassified']['rows']->isNotEmpty();
        $reconciled = AccountingWorkflow::isBalanced($calculatedEnding, $endingCash);
        $summary = [
            'beginning_cash' => $beginningCash,
            'net_change' => $netChange,
            'calculated_ending_cash' => $calculatedEnding,
            'ending_cash' => $endingCash,
            'balance_sheet_cash' => $endingCash,
            'reconciliation_difference' => $difference,
        ];

        return [
            'sections' => $sections,
            'summary' => $summary,
            'display_summary' => collect($summary)
                ->map(fn (string $amount): string => FinancialReportingConvention::round($amount))->all(),
            'reconciled' => $reconciled,
            'has_unclassified' => $hasUnclassified,
            'final_ready' => $reconciled && ! $hasUnclassified,
        ];
    }

    /** @param array<string, mixed> $filters
     * @return array{rows: LengthAwarePaginator, total: string, display_total: string}
     */
    public function drilldown(array $filters, Account $account): array
    {
        if (! $this->isCashFlowAccount($account)) {
            abort(404);
        }

        $query = $this->lineQuery($filters)
            ->where('journal_entry_lines.account_id', $account->id);
        $amounts = (clone $query)->reorder()->toBase()
            ->selectRaw('COALESCE(SUM(journal_entry_lines.debit), 0) AS debit')
            ->selectRaw('COALESCE(SUM(journal_entry_lines.credit), 0) AS credit')
            ->first();
        $total = $this->cashFlowEffect(
            $this->decimal($amounts->debit),
            $this->decimal($amounts->credit),
        );

        return [
            'rows' => $query->with([
                'journalEntry:id,journal_number,journal_date,source_type,source_id,reference_number,description,status',
                'account:id,code,name,cash_flow_classification',
            ])->orderBy('journal_entries.journal_date')->orderBy('journal_entry_lines.journal_entry_id')
                ->orderBy('journal_entry_lines.line_number')->paginate(50)->withQueryString(),
            'total' => $total,
            'display_total' => FinancialReportingConvention::round($total),
        ];
    }

    /** @return Collection<int, Account> */
    public function mappingReview(): Collection
    {
        return $this->accounts()
            ->sortBy(fn (Account $account): string => $this->mappingValue($account).$account->code)
            ->values();
    }

    /** @return Collection<int, Account> */
    private function accounts(): Collection
    {
        return Account::query()
            ->where('is_postable', true)
            ->where('account_type', '!=', AccountType::Cash)
            ->whereIn('account_class', [AccountClass::Asset, AccountClass::Liability, AccountClass::OwnerEquity])
            ->ordered()
            ->get([
                'id', 'code', 'name', 'account_class', 'account_type', 'normal_balance',
                'cash_flow_classification', 'control_account_type', 'is_postable',
            ]);
    }

    /** @param array<string, mixed> $filters
     * @param  Collection<int, Account>  $accounts
     * @return Collection<int, array{debit: string, credit: string}>
     */
    private function activity(array $filters, Collection $accounts): Collection
    {
        return $this->lineQuery($filters)
            ->whereIn('journal_entry_lines.account_id', $accounts->pluck('id'))
            ->reorder()
            ->select('journal_entry_lines.account_id')
            ->selectRaw('COALESCE(SUM(journal_entry_lines.debit), 0) AS debit')
            ->selectRaw('COALESCE(SUM(journal_entry_lines.credit), 0) AS credit')
            ->groupBy('journal_entry_lines.account_id')
            ->get()
            ->mapWithKeys(fn (JournalEntryLine $line): array => [
                $line->account_id => [
                    'debit' => $this->decimal($line->getAttribute('debit')),
                    'credit' => $this->decimal($line->getAttribute('credit')),
                ],
            ]);
    }

    /** @param Collection<int, Account> $accounts
     * @param  Collection<int, array{debit: string, credit: string}>  $activity
     * @return Collection<int, array<string, mixed>>
     */
    private function operatingRows(Collection $accounts, Collection $activity, string $netIncome): Collection
    {
        $rows = collect([$this->netIncomeRow($netIncome)]);

        $operatingAccounts = $accounts->where('cash_flow_classification', CashFlowClassification::Operating);
        foreach ($operatingAccounts as $account) {
            $amount = $this->accountEffect($activity, $account);
            if (! $this->material($amount)) {
                continue;
            }
            $rows->push($this->row($account, $this->operatingLabel($account), $amount));
        }

        $nonCashAccounts = $accounts->where('account_type', AccountType::AccumulatedDepreciation);
        foreach ($nonCashAccounts as $account) {
            $amount = $this->accountEffect($activity, $account);
            if ($this->material($amount)) {
                $rows->push($this->row($account, 'Non-cash adjustment — '.$account->name, $amount));
            }
        }

        return $rows;
    }

    /** @param Collection<int, Account> $accounts
     * @param  Collection<int, array{debit: string, credit: string}>  $activity
     * @return Collection<int, array<string, mixed>>
     */
    private function classifiedRows(
        Collection $accounts,
        Collection $activity,
        CashFlowClassification $classification,
    ): Collection {
        return $accounts
            ->where('cash_flow_classification', $classification)
            ->reject(fn (Account $account): bool => $account->account_type === AccountType::AccumulatedDepreciation)
            ->map(fn (Account $account): array => $this->row(
                $account,
                $this->classifiedLabel($account),
                $this->accountEffect($activity, $account),
            ))
            ->filter(fn (array $row): bool => $this->material($row['amount']))
            ->values();
    }

    /** @param Collection<int, Account> $accounts
     * @param  Collection<int, array{debit: string, credit: string}>  $activity
     * @return Collection<int, array<string, mixed>>
     */
    private function unclassifiedRows(Collection $accounts, Collection $activity): Collection
    {
        return $accounts->whereNull('cash_flow_classification')
            ->map(fn (Account $account): array => $this->row(
                $account,
                'Unclassified — '.$account->name,
                $this->accountEffect($activity, $account),
            ))
            ->filter(fn (array $row): bool => $this->material($row['amount']))
            ->values();
    }

    /** @return array<string, mixed> */
    private function row(Account $account, string $label, string $amount): array
    {
        return [
            'account' => $account,
            'label' => $label,
            'amount' => $amount,
            'display_amount' => FinancialReportingConvention::round($amount),
        ];
    }

    private function operatingLabel(Account $account): string
    {
        return match ($account->account_type) {
            AccountType::AccountsReceivable => 'Change in accounts receivable',
            AccountType::Inventory => 'Change in inventory',
            AccountType::PrepaidExpense => 'Change in prepaid expenses and other current assets',
            AccountType::AccountsPayable => 'Change in accounts payable',
            AccountType::AccruedLiability, AccountType::TaxPayable => 'Change in accrued and tax liabilities — '.$account->name,
            default => 'Other operating activity — '.$account->name,
        };
    }

    private function classifiedLabel(Account $account): string
    {
        return match ($account->account_type) {
            AccountType::PropertyPlantEquipment => 'Purchase or disposal of long-term assets — '.$account->name,
            AccountType::OwnerCapital => 'Owner capital contributions',
            AccountType::OwnerDrawings => 'Owner drawings',
            AccountType::LoansPayable => 'Loan proceeds or repayments',
            default => 'Other '.$this->mappingValue($account).' activity — '.$account->name,
        };
    }

    /** @return array<string, mixed> */
    private function netIncomeRow(string $netIncome): array
    {
        return [
            'account' => null,
            'label' => 'Net income',
            'amount' => $netIncome,
            'display_amount' => FinancialReportingConvention::round($netIncome),
        ];
    }

    private function mappingValue(Account $account): string
    {
        return $account->cash_flow_classification instanceof CashFlowClassification
            ? $account->cash_flow_classification->value
            : 'unclassified';
    }

    /** @param Collection<int, array{debit: string, credit: string}> $activity */
    private function accountEffect(Collection $activity, Account $account): string
    {
        $amounts = $activity->get($account->id, ['debit' => '0.0000', 'credit' => '0.0000']);

        return $this->cashFlowEffect($amounts['debit'], $amounts['credit']);
    }

    private function cashFlowEffect(string $debit, string $credit): string
    {
        return bcsub($credit, $debit, 4);
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
            ->whereDate('journal_entries.journal_date', '>=', $filters['start_date'])
            ->whereDate('journal_entries.journal_date', '<=', $filters['end_date']);
    }

    private function cashBalance(string $date, bool $inclusive): string
    {
        $amounts = JournalEntryLine::query()
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_entry_lines.journal_entry_id')
            ->join('accounts', 'accounts.id', '=', 'journal_entry_lines.account_id')
            ->whereIn('journal_entries.status', FinancialReportingConvention::SOURCE_STATUSES)
            ->where('accounts.account_type', AccountType::Cash)
            ->whereDate('journal_entries.journal_date', $inclusive ? '<=' : '<', $date)
            ->toBase()
            ->selectRaw('COALESCE(SUM(journal_entry_lines.debit), 0) AS debit')
            ->selectRaw('COALESCE(SUM(journal_entry_lines.credit), 0) AS credit')
            ->first();

        return bcsub($this->decimal($amounts->debit), $this->decimal($amounts->credit), 4);
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

    private function isCashFlowAccount(Account $account): bool
    {
        return $account->is_postable
            && $account->account_type !== AccountType::Cash
            && in_array($account->account_class, [
                AccountClass::Asset, AccountClass::Liability, AccountClass::OwnerEquity,
            ], true);
    }

    private function material(string $amount): bool
    {
        return bccomp($amount, self::MATERIALITY, 4) >= 0
            || bccomp($amount, bcsub('0', self::MATERIALITY, 4), 4) <= 0;
    }

    private function decimal(mixed $value): string
    {
        return bcadd('0', (string) ($value ?? '0'), 4);
    }
}
