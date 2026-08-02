<?php

namespace App\Services;

use App\Enums\JournalEntryStatus;
use App\Enums\JournalEntryType;
use App\Models\DocumentSequence;
use App\Models\InventoryOpeningBalance;
use App\Models\JournalEntry;
use App\Models\ProductionCutover;
use App\Reports\SubledgerReconciliationReport;
use App\Reports\TrialBalanceReport;

class ProductionCutoverReport
{
    public function __construct(private TrialBalanceReport $trialBalance, private SubledgerReconciliationReport $subledgers) {}

    /** @return array<string, mixed> */
    public function generate(ProductionCutover $cutover): array
    {
        $date = $cutover->cutover_date->toDateString();
        $filters = ['start_date' => $date, 'end_date' => $date, 'as_of' => $date, 'fiscal_period_id' => null, 'basis' => 'adjusted', 'account_id' => null, 'detail' => 'postable'];
        $trial = $this->trialBalance->generate($filters, false);
        $subledgers = $this->subledgers->generate($filters);
        $available = $subledgers->where('available', true);
        $unreconciled = $available->filter(fn (array $row): bool => bccomp((string) $row['difference'], '0', 4) !== 0);
        $backup = $cutover->backupRun;

        return [
            'cutover_date' => $date,
            'opening_journal_count' => JournalEntry::query()->where('journal_type', JournalEntryType::Opening)->whereIn('status', [JournalEntryStatus::Posted, JournalEntryStatus::Reversed])->whereDate('journal_date', $date)->count(),
            'inventory_opening_batch_count' => InventoryOpeningBalance::query()->where('status', 'posted')->whereDate('opening_date', $date)->count(),
            'trial_balance' => ['debit' => $trial['totals']['closing_debit'], 'credit' => $trial['totals']['closing_credit'], 'balanced' => $trial['balanced']],
            'subledgers' => ['checked' => $available->count(), 'unreconciled' => $unreconciled->map(fn (array $row): array => ['account' => $row['account']->code, 'difference' => $row['difference']])->values()->all()],
            'active_sequence_count' => DocumentSequence::query()->where('active', true)->count(),
            'backup' => ['id' => $backup->id, 'status' => $backup->status, 'offsite_copied' => $backup->offsite_copied, 'restore_tested' => $backup->restore_tested_at !== null],
            'confirmations' => ['cash' => $cutover->cash_confirmed, 'owner_equity' => $cutover->owner_equity_confirmed, 'sequences' => $cutover->sequence_confirmed, 'tax_controls' => $cutover->tax_control_confirmed],
        ];
    }

    /** @param array<string, mixed> $report */
    public function failures(array $report): array
    {
        return array_values(array_filter([
            $report['opening_journal_count'] < 1 ? 'At least one posted opening journal is required on the cutover date.' : null,
            ! $report['trial_balance']['balanced'] ? 'The opening trial balance is not balanced.' : null,
            $report['subledgers']['unreconciled'] !== [] ? 'One or more available subledgers do not reconcile.' : null,
            $report['active_sequence_count'] < 1 ? 'At least one active document sequence is required.' : null,
            $report['backup']['status'] !== 'verified' || ! $report['backup']['offsite_copied'] || ! $report['backup']['restore_tested'] ? 'The selected backup must be verified, copied off-site, and restore-tested.' : null,
            in_array(false, $report['confirmations'], true) ? 'All reviewer confirmations are required.' : null,
        ]));
    }
}
