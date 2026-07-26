<?php

namespace App\Actions;

use App\Enums\AccountingSourceType;
use App\Enums\JournalEntryType;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use Illuminate\Support\Facades\DB;

class CorrectJournalEntry
{
    public function __construct(
        private ReverseJournalEntry $reverseJournal,
        private SaveJournalEntry $saveJournal,
    ) {}

    /** @return array{reversal: JournalEntry, replacement: JournalEntry} */
    public function handle(JournalEntry $original, string $date, int $fiscalPeriodId, string $reason, int $userId): array
    {
        return DB::transaction(function () use ($original, $date, $fiscalPeriodId, $reason, $userId): array {
            $original->load('lines');
            $reversal = $this->reverseJournal->handle($original, $date, $fiscalPeriodId, $reason, $userId);
            $replacement = $this->saveJournal->handle([
                'journal_number' => 'COR-'.$original->id,
                'journal_date' => $date,
                'document_date' => $original->document_date->toDateString(),
                'fiscal_period_id' => $fiscalPeriodId,
                'journal_type' => JournalEntryType::Adjustment,
                'source_type' => AccountingSourceType::Manual,
                'source_id' => null,
                'reference_number' => $original->journal_number,
                'description' => 'Correction of '.$original->journal_number.': '.$reason,
                'correction_of_id' => $original->id,
                'lines' => $original->lines->map(fn (JournalEntryLine $line): array => [
                    'account_id' => $line->account_id,
                    'description' => $line->description,
                    'debit' => $line->debit,
                    'credit' => $line->credit,
                    'customer_id' => $line->customer_id,
                    'supplier_id' => $line->supplier_id,
                    'financial_account_id' => $line->financial_account_id,
                    'warehouse_id' => $line->warehouse_id,
                    'product_id' => $line->product_id,
                    'source_line_type' => $line->source_line_type,
                    'source_line_id' => $line->source_line_id,
                ])->all(),
            ], $userId);

            return compact('reversal', 'replacement');
        }, 3);
    }
}
