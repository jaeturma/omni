<?php

namespace App\Actions;

use App\Enums\PurchaseOrderStatus;
use App\Models\PurchaseOrder;
use App\Models\ReceivingRecord;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SaveReceivingRecord
{
    /** @param array<string, mixed> $data */
    public function handle(array $data, int $userId): ReceivingRecord
    {
        return DB::transaction(function () use ($data, $userId): ReceivingRecord {
            $order = PurchaseOrder::query()->with('lines')->findOrFail($data['purchase_order_id']);
            if (! in_array($order->status, [PurchaseOrderStatus::Approved, PurchaseOrderStatus::Issued, PurchaseOrderStatus::PartiallyReceived], true)) {
                throw ValidationException::withMessages(['purchase_order_id' => 'Only approved, issued, or partially received purchase orders may be received.']);
            }
            $orderLines = $order->lines->keyBy('id');
            $lines = [];
            foreach ($data['lines'] as $position => $input) {
                if (bccomp((string) $input['received_quantity'], '0', 4) === 0) {
                    continue;
                }
                $orderLine = $orderLines->get($input['purchase_order_line_id']);
                if (! $orderLine) {
                    throw ValidationException::withMessages(['lines' => 'Every receiving line must belong to the selected purchase order.']);
                }
                if (bccomp(bcadd((string) $input['accepted_quantity'], (string) $input['rejected_quantity'], 4), (string) $input['received_quantity'], 4) === 1) {
                    throw ValidationException::withMessages(["lines.{$position}.accepted_quantity" => 'Accepted and rejected quantities cannot exceed the received quantity.']);
                }
                if (bccomp((string) $input['rejected_quantity'], '0', 4) === 1 && empty($input['rejection_reason'])) {
                    throw ValidationException::withMessages(["lines.{$position}.rejection_reason" => 'A rejection reason is required for rejected quantities.']);
                }
                $lines[] = ['purchase_order_line_id' => $orderLine->id, 'line_number' => $position + 1, 'item_type' => $orderLine->item_type, 'sku' => $orderLine->sku, 'description' => $orderLine->description, 'uom_code' => $orderLine->uom_code, 'uom_name' => $orderLine->uom_name, 'received_quantity' => $input['received_quantity'], 'accepted_quantity' => $input['accepted_quantity'], 'rejected_quantity' => $input['rejected_quantity'], 'credited_quantity' => '0.0000', 'rejection_reason' => $input['rejection_reason'] ?? null, 'notes' => $input['notes'] ?? null];
            }
            if ($lines === []) {
                throw ValidationException::withMessages(['lines' => 'Enter a received quantity for at least one purchase-order line.']);
            }
            unset($data['lines']);
            $record = ReceivingRecord::query()->create($data + ['supplier_id' => $order->supplier_id, 'supplier_name' => $order->supplier_name, 'created_by' => $userId, 'updated_by' => $userId]);
            $record->lines()->createMany($lines);

            return $record->load(['purchaseOrder', 'lines']);
        });
    }
}
