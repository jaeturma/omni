<?php

namespace App\Actions;

use App\Enums\PurchaseRequestStatus;
use App\Models\DocumentSequence;
use App\Models\PurchaseRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TransitionPurchaseRequest
{
    public function __construct(private IssueDocumentNumber $issueNumber) {}

    public function handle(PurchaseRequest $request, PurchaseRequestStatus $target, int $userId, ?string $reason = null): PurchaseRequest
    {
        return DB::transaction(function () use ($request, $target, $userId, $reason): PurchaseRequest {
            $locked = PurchaseRequest::query()->lockForUpdate()->findOrFail($request->id);
            if (! $locked->status->canTransitionTo($target)) {
                throw ValidationException::withMessages(['status' => 'This purchase request status transition is not allowed.']);
            }
            $changes = ['status' => $target, 'updated_by' => $userId];
            if ($target === PurchaseRequestStatus::Submitted) {
                $sequence = DocumentSequence::query()->where('document_type', 'purchase_request')->where('active', true)
                    ->whereHas('fiscalYear', fn ($query) => $query->whereDate('starts_on', '<=', $locked->request_date)->whereDate('ends_on', '>=', $locked->request_date))->first();
                if (! $sequence) {
                    throw ValidationException::withMessages(['status' => 'Configure an active purchase request sequence for this request date.']);
                }
                $reservation = $this->issueNumber->handle($sequence, $userId);
                $changes += ['request_number' => $reservation->document_number, 'document_number_reservation_id' => $reservation->id, 'submitted_at' => now(), 'submitted_by' => $userId];
            }
            $prefix = match ($target) {
                PurchaseRequestStatus::Approved => 'approved', PurchaseRequestStatus::Rejected => 'rejected',
                PurchaseRequestStatus::Converted => 'converted', PurchaseRequestStatus::Cancelled => 'cancelled', default => null,
            };
            if ($prefix) {
                $changes[$prefix.'_at'] = now();
                $changes[$prefix.'_by'] = $userId;
            }
            if ($target === PurchaseRequestStatus::Rejected) {
                $changes['rejection_reason'] = $reason;
            }
            if ($target === PurchaseRequestStatus::Cancelled) {
                $changes['cancellation_reason'] = $reason;
            }
            $locked->update($changes);

            return $locked->fresh(['requester', 'lines', 'canvassQuotations']);
        }, 3);
    }
}
