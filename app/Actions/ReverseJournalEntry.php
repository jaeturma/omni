<?php

namespace App\Actions;

use App\Enums\AccountingSourceType;
use App\Enums\JournalEntryStatus;
use App\Enums\JournalEntryType;
use App\Models\FiscalPeriod;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use App\Support\AccountingWorkflow;
use DomainException;
use Illuminate\Support\Facades\DB;

class ReverseJournalEntry
{
    public function __construct(
        private SaveJournalEntry $saveJournal,
        private TransitionJournalEntry $transitionJournal,
    ) {}

    public function handle(
        JournalEntry $original,
        string $reversalDate,
        int $fiscalPeriodId,
        string $reason,
        int $userId,
        bool $automatic = false,
    ): JournalEntry {
        return DB::transaction(function () use ($original, $reversalDate, $fiscalPeriodId, $reason, $userId, $automatic): JournalEntry {
            $original = JournalEntry::query()->with('lines')->lockForUpdate()->findOrFail($original->id);
            if ($original->status !== JournalEntryStatus::Posted || $original->reversal_entry_id !== null
                || JournalEntry::query()->where('reverses_entry_id', $original->id)->exists()) {
                throw new DomainException('Only an unreversed posted journal entry may be reversed.');
            }
            if (blank($reason)) {
                throw new DomainException('A reversal reason is required.');
            }

            $period = FiscalPeriod::query()->lockForUpdate()->findOrFail($fiscalPeriodId);
            AccountingWorkflow::assertPostingPeriod($period, $reversalDate);
            if ($automatic) {
                $originalPeriod = FiscalPeriod::query()->findOrFail($original->fiscal_period_id);
                $nextOpenPeriod = FiscalPeriod::query()->where('status', 'open')
                    ->whereDate('starts_on', '>', $originalPeriod->ends_on)
                    ->oldest('starts_on')
                    ->first();
                if (! $nextOpenPeriod || ! $nextOpenPeriod->is($period) || $original->journal_date->gte($reversalDate)) {
                    throw new DomainException('An auto-reversal must use the next open period and a future date.');
                }
            }

            $reversal = $this->saveJournal->handle([
                'journal_number' => 'REV-'.$original->id,
                'journal_date' => $reversalDate,
                'document_date' => $original->document_date->toDateString(),
                'fiscal_period_id' => $period->id,
                'journal_type' => JournalEntryType::Reversal,
                'source_type' => AccountingSourceType::Manual,
                'source_id' => null,
                'reference_number' => $original->journal_number,
                'description' => 'Reversal of '.$original->journal_number.': '.$reason,
                'reverses_entry_id' => $original->id,
                'reversal_reason' => $reason,
                'is_auto_reversal' => $automatic,
                'lines' => $original->lines->map(fn (JournalEntryLine $line): array => [
                    'account_id' => $line->account_id,
                    'description' => $line->description,
                    'debit' => $line->credit,
                    'credit' => $line->debit,
                    'customer_id' => $line->customer_id,
                    'supplier_id' => $line->supplier_id,
                    'financial_account_id' => $line->financial_account_id,
                    'warehouse_id' => $line->warehouse_id,
                    'product_id' => $line->product_id,
                    'source_line_type' => $line->source_line_type,
                    'source_line_id' => $line->source_line_id,
                ])->all(),
            ], $userId);
            $reversal = $this->transitionJournal->handle($reversal, JournalEntryStatus::Posted, $userId);

            $original->forceFill([
                'status' => JournalEntryStatus::Reversed,
                'reversed_at' => now(),
                'reversed_by' => $userId,
                'reversal_entry_id' => $reversal->id,
                'reversal_reason' => $reason,
                'auto_reverse_on' => $automatic ? $reversalDate : null,
            ])->saveQuietly();

            return $reversal->refresh();
        }, 3);
    }
}
