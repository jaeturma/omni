<?php

namespace App\Services;

use App\Enums\BankReconciliationStatus;
use App\Enums\JournalEntryStatus;
use App\Enums\JournalEntryType;
use App\Models\BankReconciliation;
use App\Models\FiscalPeriod;
use App\Models\JournalEntry;
use App\Models\SourcePosting;
use App\Reports\SubledgerReconciliationReport;

class PeriodCloseChecklist
{
    public function __construct(private SubledgerReconciliationReport $reconciliations) {}

    /** @return array<string, array{label: string, severity: string, count: int, passed: bool, overrideable: bool}> */
    public function generate(FiscalPeriod $period): array
    {
        $reconciliations = $this->reconciliations->generate([
            'start_date' => $period->starts_on->toDateString(),
            'end_date' => $period->ends_on->toDateString(),
            'as_of' => $period->ends_on->toDateString(),
            'basis' => 'adjusted',
            'detail' => 'postable',
        ]);
        $difference = fn (string $type): int => $reconciliations
            ->where('account.control_account_type', $type)
            ->filter(fn (array $row): bool => $row['available'] && bccomp($row['difference'], '0', 4) !== 0)
            ->count();
        $cashDifferences = $reconciliations
            ->filter(fn (array $row): bool => in_array($row['account']->control_account_type, ['cash_on_hand', 'petty_cash', 'cash_in_bank', 'e_wallet'], true))
            ->filter(fn (array $row): bool => $row['available'] && bccomp($row['difference'], '0', 4) !== 0)
            ->count();

        return [
            'unposted_journals' => $this->item('Unposted journal entries', $this->journals($period)
                ->where('status', JournalEntryStatus::Draft)->where('journal_type', '!=', JournalEntryType::Adjustment)->count()),
            'failed_source_postings' => $this->item('Failed source postings', SourcePosting::query()->where('status', 'failed')
                ->where(fn ($query) => $query->whereNull('journal_entry_id')->orWhereIn('journal_entry_id', $this->journals($period)->select('id')))->count()),
            'unbalanced_journals' => $this->item('Unbalanced posted journal entries', $this->journals($period)
                ->whereIn('status', [JournalEntryStatus::Posted, JournalEntryStatus::Reversed])
                ->whereColumn('total_debit', '!=', 'total_credit')->count()),
            'ar_difference' => $this->item('Accounts receivable reconciliation differences', $difference('accounts_receivable')),
            'ap_difference' => $this->item('Accounts payable reconciliation differences', $difference('accounts_payable')),
            'cash_difference' => $this->item('Cash control-account reconciliation differences', $cashDifferences),
            'bank_difference' => $this->item('Bank reconciliation differences', BankReconciliation::query()
                ->whereBetween('statement_end_date', [$period->starts_on, $period->ends_on])
                ->where(fn ($query) => $query->where('status', '!=', BankReconciliationStatus::Finalized)
                    ->orWhere('reconciliation_difference', '!=', 0))->count()),
            'inventory_difference' => $this->item('Inventory reconciliation differences', $difference('inventory')),
            'unresolved_reversals' => $this->item('Unresolved reversal issues', $this->journals($period)
                ->where(fn ($query) => $query
                    ->where(fn ($query) => $query->where('status', JournalEntryStatus::Reversed)->whereNull('reversal_entry_id'))
                    ->orWhere(fn ($query) => $query->whereNotNull('auto_reverse_on')->whereDate('auto_reverse_on', '<=', $period->ends_on)->whereNull('reversal_entry_id')))
                ->count()),
            'open_adjustments' => $this->item('Open adjustment journals', $this->journals($period)
                ->where('status', JournalEntryStatus::Draft)->where('journal_type', JournalEntryType::Adjustment)->count(), 'warning', true),
            'outside_period_dates' => $this->item('Transactions dated outside the assigned period', JournalEntry::query()
                ->where('fiscal_period_id', $period->id)
                ->where(fn ($query) => $query->whereDate('journal_date', '<', $period->starts_on)
                    ->orWhereDate('journal_date', '>', $period->ends_on))->count()),
        ];
    }

    /** @param array<string, array{label: string, severity: string, count: int, passed: bool, overrideable: bool}> $checklist */
    public function hasBlockingFailures(array $checklist, array $overrides = []): bool
    {
        foreach ($checklist as $key => $item) {
            if (! $item['passed'] && (! $item['overrideable'] || ! in_array($key, $overrides, true))) {
                return true;
            }
        }

        return false;
    }

    /** @return array{label: string, severity: string, count: int, passed: bool, overrideable: bool} */
    private function item(string $label, int $count, string $severity = 'critical', bool $overrideable = false): array
    {
        return compact('label', 'severity', 'count', 'overrideable') + ['passed' => $count === 0];
    }

    private function journals(FiscalPeriod $period)
    {
        return JournalEntry::query()->where('fiscal_period_id', $period->id);
    }
}
