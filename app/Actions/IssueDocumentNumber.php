<?php

namespace App\Actions;

use App\Models\DocumentNumberReservation;
use App\Models\DocumentSequence;
use DomainException;
use Illuminate\Support\Facades\DB;

class IssueDocumentNumber
{
    public function handle(DocumentSequence $sequence, int $userId): DocumentNumberReservation
    {
        return DB::transaction(function () use ($sequence, $userId): DocumentNumberReservation {
            $locked = DocumentSequence::query()->with('fiscalYear')->lockForUpdate()->findOrFail($sequence->id);
            if (! $locked->active) {
                throw new DomainException('Inactive document sequences cannot issue numbers.');
            }

            $number = $locked->current_number + 1;
            $reservation = $locked->reservations()->create([
                'fiscal_year_id' => $locked->fiscal_year_id, 'number' => $number,
                'document_number' => $locked->formatNumber($number), 'issued_at' => now(), 'issued_by' => $userId,
            ]);
            $locked->update(['current_number' => $number, 'updated_by' => $userId]);

            return $reservation;
        }, 3);
    }
}
