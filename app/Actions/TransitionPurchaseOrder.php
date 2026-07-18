<?php

namespace App\Actions;

use App\Enums\PurchaseOrderStatus;
use App\Models\DocumentSequence;
use App\Models\PurchaseOrder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TransitionPurchaseOrder
{
    public function __construct(private IssueDocumentNumber $issueNumber) {}

    public function handle(PurchaseOrder $order, PurchaseOrderStatus $target, int $userId, ?string $reason = null): PurchaseOrder
    {
        return DB::transaction(function () use ($order, $target, $userId, $reason): PurchaseOrder {
            $locked = PurchaseOrder::query()->lockForUpdate()->findOrFail($order->id);
            if (! $locked->status->canTransitionTo($target)) {
                throw ValidationException::withMessages(['status' => 'This purchase-order status transition is not allowed.']);
            }
            $changes = ['status' => $target, 'updated_by' => $userId];
            if ($target === PurchaseOrderStatus::Approved) {
                $sequence = DocumentSequence::query()->where('document_type', 'purchase_order')->where('active', true)->whereHas('fiscalYear', fn ($query) => $query->whereDate('starts_on', '<=', $locked->order_date)->whereDate('ends_on', '>=', $locked->order_date))->first();
                if (! $sequence) {
                    throw ValidationException::withMessages(['status' => 'Configure an active purchase-order sequence for this order date.']);
                }
                $reservation = $this->issueNumber->handle($sequence, $userId);
                $changes += ['purchase_order_number' => $reservation->document_number, 'document_number_reservation_id' => $reservation->id, 'approved_at' => now(), 'approved_by' => $userId];
            }
            if ($target === PurchaseOrderStatus::Issued) {
                $changes += ['issued_at' => now(), 'issued_by' => $userId];
            }
            if ($target === PurchaseOrderStatus::Closed) {
                $changes += ['closed_at' => now(), 'closed_by' => $userId];
            }
            if ($target === PurchaseOrderStatus::Cancelled) {
                $changes += ['cancelled_at' => now(), 'cancelled_by' => $userId, 'cancellation_reason' => $reason];
            }
            $locked->update($changes);

            return $locked->fresh(['supplier', 'lines']);
        }, 3);
    }
}
