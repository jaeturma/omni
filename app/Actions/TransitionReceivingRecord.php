<?php

namespace App\Actions;

use App\Enums\PurchaseOrderStatus;
use App\Enums\ReceivingStatus;
use App\Models\DocumentSequence;
use App\Models\PurchaseOrder;
use App\Models\ReceivingRecord;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TransitionReceivingRecord
{
    public function __construct(private IssueDocumentNumber $issueNumber, private PostPurchaseReceiptInventory $inventory) {}

    public function handle(ReceivingRecord $record, ReceivingStatus $target, int $userId, ?string $reason = null): ReceivingRecord
    {
        return DB::transaction(function () use ($record, $target, $userId, $reason): ReceivingRecord {
            $locked = ReceivingRecord::query()->with('lines')->lockForUpdate()->findOrFail($record->id);
            if (! $locked->status->canTransitionTo($target)) {
                throw ValidationException::withMessages(['status' => 'This receiving status transition is not allowed.']);
            }
            $order = PurchaseOrder::query()->with('lines')->lockForUpdate()->findOrFail($locked->purchase_order_id);
            $changes = ['status' => $target, 'updated_by' => $userId];
            if ($target === ReceivingStatus::Received) {
                $sequence = DocumentSequence::query()->where('document_type', 'receiving_report')->where('active', true)->whereHas('fiscalYear', fn ($query) => $query->whereDate('starts_on', '<=', $locked->receiving_date)->whereDate('ends_on', '>=', $locked->receiving_date))->first();
                if (! $sequence) {
                    throw ValidationException::withMessages(['status' => 'Configure an active receiving-report sequence for this receiving date.']);
                }
                foreach ($locked->lines as $line) {
                    $orderLine = $order->lines->firstWhere('id', $line->purchase_order_line_id);
                    $remaining = bcsub(bcsub($orderLine->ordered_quantity, $orderLine->received_quantity, 4), $orderLine->cancelled_quantity, 4);
                    if (bccomp($line->received_quantity, $remaining, 4) === 1) {
                        throw ValidationException::withMessages(['lines' => "Receipt quantity for {$line->description} exceeds the purchase-order remainder."]);
                    }
                    $orderLine->update(['received_quantity' => bcadd($orderLine->received_quantity, $line->received_quantity, 4)]);
                    $line->update(['credited_quantity' => $line->received_quantity]);
                }
                $reservation = $this->issueNumber->handle($sequence, $userId);
                $changes += ['receiving_number' => $reservation->document_number, 'document_number_reservation_id' => $reservation->id, 'purchase_order_status_before_receipt' => $order->status->value, 'received_at' => now()];
            }
            if ($target === ReceivingStatus::Inspected) {
                $changes += ['inspected_at' => now(), 'inspected_by' => $userId];
            }
            if (in_array($target, [ReceivingStatus::Accepted, ReceivingStatus::PartiallyAccepted, ReceivingStatus::Rejected], true)) {
                $this->validateDisposition($locked, $target);
                foreach ($locked->lines as $line) {
                    $orderLine = $order->lines->firstWhere('id', $line->purchase_order_line_id);
                    $delta = bcsub($line->accepted_quantity, $line->credited_quantity, 4);
                    $orderLine->update(['received_quantity' => bcadd($orderLine->received_quantity, $delta, 4)]);
                    $line->update(['credited_quantity' => $line->accepted_quantity]);
                }
                if ($target !== ReceivingStatus::Rejected) {
                    $this->inventory->post($locked, $userId);
                }
                $changes += ['accepted_at' => now(), 'accepted_by' => $userId];
            }
            if ($target === ReceivingStatus::Cancelled) {
                if (in_array($locked->status, [ReceivingStatus::Accepted, ReceivingStatus::PartiallyAccepted], true)) {
                    $this->inventory->reverse($locked, $userId);
                }
                foreach ($locked->lines as $line) {
                    $orderLine = $order->lines->firstWhere('id', $line->purchase_order_line_id);
                    $orderLine->update(['received_quantity' => bcsub($orderLine->received_quantity, $line->credited_quantity, 4)]);
                    $line->update(['credited_quantity' => '0.0000']);
                }
                $changes += ['cancelled_at' => now(), 'cancelled_by' => $userId, 'cancellation_reason' => $reason];
            }
            $locked->update($changes);
            $this->syncOrderStatus($order, $locked, $userId);

            return $locked->fresh(['purchaseOrder', 'supplier', 'lines']);
        }, 3);
    }

    private function validateDisposition(ReceivingRecord $record, ReceivingStatus $target): void
    {
        $accepted = $rejected = $received = '0.0000';
        foreach ($record->lines as $line) {
            $accepted = bcadd($accepted, $line->accepted_quantity, 4);
            $rejected = bcadd($rejected, $line->rejected_quantity, 4);
            $received = bcadd($received, $line->received_quantity, 4);
        }
        if (bccomp(bcadd($accepted, $rejected, 4), $received, 4) !== 0) {
            throw ValidationException::withMessages(['status' => 'All received quantities must be accepted or rejected before disposition.']);
        }
        $valid = match ($target) {
            ReceivingStatus::Accepted => bccomp($rejected, '0', 4) === 0, ReceivingStatus::Rejected => bccomp($accepted, '0', 4) === 0, ReceivingStatus::PartiallyAccepted => bccomp($accepted, '0', 4) === 1 && bccomp($rejected, '0', 4) === 1, default => false
        };
        if (! $valid) {
            throw ValidationException::withMessages(['status' => 'The selected disposition does not match accepted and rejected quantities.']);
        }
    }

    private function syncOrderStatus(PurchaseOrder $order, ReceivingRecord $record, int $userId): void
    {
        $order->load('lines');
        $hasReceived = false;
        $fullyReceived = true;
        foreach ($order->lines as $line) {
            $hasReceived = $hasReceived || bccomp($line->received_quantity, '0', 4) === 1;
            $fullyReceived = $fullyReceived && bccomp(bcadd($line->received_quantity, $line->cancelled_quantity, 4), $line->ordered_quantity, 4) >= 0;
        }
        $fallback = $record->purchase_order_status_before_receipt ? PurchaseOrderStatus::from($record->purchase_order_status_before_receipt) : $order->status;
        $status = $fullyReceived ? PurchaseOrderStatus::FullyReceived : ($hasReceived ? PurchaseOrderStatus::PartiallyReceived : $fallback);
        $order->update(['status' => $status, 'updated_by' => $userId]);
    }
}
