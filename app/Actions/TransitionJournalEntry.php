<?php

namespace App\Actions;

use App\Enums\JournalEntryStatus;
use App\Models\Account;
use App\Models\FiscalPeriod;
use App\Models\JournalEntry;
use App\Support\AccountingWorkflow;
use DomainException;
use Illuminate\Support\Facades\DB;

class TransitionJournalEntry
{
    public function handle(JournalEntry $entry, JournalEntryStatus $target, int $userId, ?string $reason = null): JournalEntry
    {
        return DB::transaction(function () use ($entry, $target, $userId, $reason): JournalEntry {
            $entry = JournalEntry::query()->with(['lines.account', 'fiscalPeriod'])->lockForUpdate()->findOrFail($entry->id);
            $currentStatus = JournalEntryStatus::from((string) $entry->getRawOriginal('status'));
            if (! $currentStatus->canTransitionTo($target)) {
                throw new DomainException('The requested journal status transition is not allowed.');
            }
            if ($target === JournalEntryStatus::Posted) {
                $period = FiscalPeriod::query()->findOrFail($entry->fiscal_period_id);
                $journalDate = (string) $entry->getRawOriginal('journal_date');
                $totalDebit = (string) $entry->getRawOriginal('total_debit');
                $totalCredit = (string) $entry->getRawOriginal('total_credit');
                AccountingWorkflow::assertPostingPeriod($period, $journalDate);
                if (! AccountingWorkflow::isBalanced($totalDebit, $totalCredit) || bccomp($totalDebit, '0', 4) <= 0) {
                    throw new DomainException('A posted journal entry must be balanced and non-zero.');
                }
                foreach ($entry->lines->pluck('account_id') as $accountId) {
                    Account::query()->findOrFail($accountId)->assertPostable();
                }
                $entry->forceFill(['status' => $target, 'posted_at' => now(), 'posted_by' => $userId])->saveQuietly();
            } elseif ($target === JournalEntryStatus::Voided) {
                if (blank($reason)) {
                    throw new DomainException('A reason is required to void a journal entry.');
                }
                $entry->forceFill(['status' => $target, 'voided_at' => now(), 'voided_by' => $userId, 'void_reason' => $reason])->saveQuietly();
            } else {
                throw new DomainException('Reversal entries are implemented in a later work package.');
            }

            return $entry->refresh();
        }, 3);
    }
}
