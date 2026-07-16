<?php

namespace App\Actions;

use App\Enums\QuotationStatus;
use App\Models\DocumentSequence;
use App\Models\Quotation;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TransitionQuotation
{
    public function __construct(private IssueDocumentNumber $issueNumber) {}

    public function handle(Quotation $quotation, QuotationStatus $target, int $userId, ?string $reason = null): Quotation
    {
        return DB::transaction(function () use ($quotation, $target, $userId, $reason): Quotation {
            $locked = Quotation::query()->lockForUpdate()->findOrFail($quotation->id);
            if (! $locked->status->canTransitionTo($target)) {
                throw ValidationException::withMessages(['status' => 'This quotation status transition is not allowed.']);
            }

            $changes = ['status' => $target, 'updated_by' => $userId];
            if ($target === QuotationStatus::Submitted) {
                $sequence = DocumentSequence::query()->where('document_type', 'quotation')->where('active', true)
                    ->whereHas('fiscalYear', fn ($query) => $query->whereDate('starts_on', '<=', $locked->quotation_date)->whereDate('ends_on', '>=', $locked->quotation_date))
                    ->first();
                if (! $sequence) {
                    throw ValidationException::withMessages(['status' => 'Configure an active quotation document sequence for this quotation date.']);
                }
                $reservation = $this->issueNumber->handle($sequence, $userId);
                $changes += ['quotation_number' => $reservation->document_number, 'document_number_reservation_id' => $reservation->id, 'submitted_at' => now(), 'submitted_by' => $userId];
            }
            $prefix = match ($target) {
                QuotationStatus::Approved => 'approved', QuotationStatus::Rejected => 'rejected',
                QuotationStatus::Expired => 'expired', QuotationStatus::Converted => 'converted',
                QuotationStatus::Cancelled => 'cancelled', default => null,
            };
            if ($prefix) {
                $changes[$prefix.'_at'] = now();
                $changes[$prefix.'_by'] = $userId;
            }
            if ($target === QuotationStatus::Rejected) {
                $changes['rejection_reason'] = $reason;
            }
            if ($target === QuotationStatus::Cancelled) {
                $changes['cancellation_reason'] = $reason;
            }
            $locked->update($changes);

            return $locked->fresh(['customer', 'lines']);
        }, 3);
    }
}
