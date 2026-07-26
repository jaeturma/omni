<?php

namespace App\Actions;

use App\Models\JournalEntry;
use Illuminate\Support\Facades\DB;

class SaveJournalEntry
{
    public function handle(array $data, int $userId, ?JournalEntry $entry = null): JournalEntry
    {
        return DB::transaction(function () use ($data, $userId, $entry): JournalEntry {
            $lines = $data['lines'];
            unset($data['lines']);
            $totalDebit = '0.0000';
            $totalCredit = '0.0000';
            foreach ($lines as $line) {
                $totalDebit = bcadd($totalDebit, (string) $line['debit'], 4);
                $totalCredit = bcadd($totalCredit, (string) $line['credit'], 4);
            }

            $entry ??= new JournalEntry;
            if ($entry->exists) {
                $entry = JournalEntry::query()->lockForUpdate()->findOrFail($entry->id);
            }
            $entry->fill($data + ['total_debit' => $totalDebit, 'total_credit' => $totalCredit, 'updated_by' => $userId]);
            if (! $entry->exists) {
                $entry->created_by = $userId;
            }
            $entry->save();
            $entry->lines()->delete();
            $entry->lines()->createMany(collect($lines)->values()->map(fn (array $line, int $index) => $line + ['line_number' => $index + 1])->all());

            return $entry->load('lines');
        }, 3);
    }
}
