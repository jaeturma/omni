<?php

namespace App\Actions;

use App\Enums\DeliveryStatus;
use App\Enums\SalesOrderStatus;
use App\Models\Delivery;
use App\Models\DocumentSequence;
use App\Models\SalesOrder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TransitionDelivery
{
    public function __construct(private IssueDocumentNumber $issue) {}

    public function handle(Delivery $d, DeliveryStatus $target, int $userId, array $data = []): Delivery
    {
        return DB::transaction(function () use ($d, $target, $userId, $data) {
            $delivery = Delivery::with('lines')->lockForUpdate()->findOrFail($d->id);
            if (! $delivery->status->canTransitionTo($target)) {
                throw ValidationException::withMessages(['status' => 'This delivery transition is not allowed.']);
            }$changes = ['status' => $target, 'updated_by' => $userId];
            if ($target === DeliveryStatus::Released) {
                $order = SalesOrder::with('lines')->lockForUpdate()->findOrFail($delivery->sales_order_id);
                foreach ($delivery->lines as $line) {
                    $orderLine = $order->lines->firstWhere('id', $line->sales_order_line_id);
                    if (bccomp($line->delivered_quantity, $orderLine->remaining_quantity, 4) === 1) {
                        throw ValidationException::withMessages(['lines' => 'Delivery quantity exceeds the order quantity remaining.']);
                    }
                    $orderLine->update([
                        'delivered_quantity' => bcadd($orderLine->delivered_quantity, $line->delivered_quantity, 4),
                    ]);
                }$seq = DocumentSequence::where('document_type', 'delivery_receipt')->where('active', true)->whereHas('fiscalYear', fn ($q) => $q->whereDate('starts_on', '<=', $delivery->delivery_date)->whereDate('ends_on', '>=', $delivery->delivery_date))->first();
                if (! $seq) {
                    throw ValidationException::withMessages(['status' => 'Configure an active delivery sequence for this date.']);
                }$r = $this->issue->handle($seq, $userId);
                $changes += ['delivery_number' => $r->document_number, 'document_number_reservation_id' => $r->id, 'released_at' => now(), 'released_by' => $userId];
                $this->syncOrderStatus($order, $userId);
            }if ($target === DeliveryStatus::Delivered) {
                $changes += ['delivered_at' => now(), 'delivered_by' => $userId, 'received_at' => $data['received_at'], 'received_by_name' => $data['received_by_name']];
            }if ($target === DeliveryStatus::Accepted) {
                $changes += ['accepted_at' => now(), 'accepted_by' => $userId, 'acceptance_notes' => $data['acceptance_notes'] ?? null];
            }if ($target === DeliveryStatus::Cancelled) {
                if ($delivery->status !== DeliveryStatus::Draft) {
                    $order = SalesOrder::with('lines')->lockForUpdate()->findOrFail($delivery->sales_order_id);
                    foreach ($delivery->lines as $line) {
                        $orderLine = $order->lines->firstWhere('id', $line->sales_order_line_id);
                        $orderLine->update([
                            'delivered_quantity' => bcsub($orderLine->delivered_quantity, $line->delivered_quantity, 4),
                        ]);
                    }$this->syncOrderStatus($order, $userId);
                }$changes += ['cancelled_at' => now(), 'cancelled_by' => $userId, 'cancellation_reason' => $data['reason']];
            }$delivery->update($changes);

            return $delivery->fresh('lines');
        }, 3);
    }

    private function syncOrderStatus(SalesOrder $o, int $userId): void
    {
        $lines = $o->lines()->get();
        $status = $lines->every(fn ($l) => bccomp(bcadd($l->delivered_quantity, $l->cancelled_quantity, 4), $l->ordered_quantity, 4) === 0) ? SalesOrderStatus::Fulfilled : ($lines->contains(fn ($l) => bccomp($l->delivered_quantity, '0', 4) === 1) ? SalesOrderStatus::PartiallyFulfilled : SalesOrderStatus::Confirmed);
        $o->update(['status' => $status, 'updated_by' => $userId]);
    }
}
