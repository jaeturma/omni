<?php

namespace App\Actions;

use App\Enums\InventoryMovementStatus;
use App\Enums\InventoryMovementType;
use App\Enums\SupplierInvoiceStatus;
use App\Models\InventoryBalance;
use App\Models\InventoryMovement;
use App\Models\ProductService;
use App\Models\ReceivingRecord;
use App\Models\ReceivingRecordLine;
use App\Models\SupplierInvoiceLine;
use App\Support\InventoryWorkflow;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PostPurchaseReceiptInventory
{
    public function post(ReceivingRecord $record, int $userId): void
    {
        DB::transaction(function () use ($record, $userId): void {
            $locked = ReceivingRecord::query()->with('lines.purchaseOrderLine')->lockForUpdate()->findOrFail($record->id);
            if (! $locked->warehouse_id) {
                throw ValidationException::withMessages(['warehouse_id' => 'An inventory receipt requires a warehouse.']);
            }

            foreach ($locked->lines as $line) {
                if (bccomp($line->accepted_quantity, '0', 4) !== 1) {
                    continue;
                }
                $productId = $line->purchaseOrderLine->product_service_id;
                $product = $productId ? ProductService::query()->lockForUpdate()->find($productId) : null;
                if (! $product || ! InventoryWorkflow::tracks($product)) {
                    continue;
                }
                if (InventoryMovement::query()->where('receiving_record_line_id', $line->id)->whereNull('reversal_of_id')->exists()) {
                    throw ValidationException::withMessages(['status' => "Receiving line {$line->line_number} has already been posted to inventory."]);
                }

                $balance = InventoryBalance::query()->where('product_service_id', $product->id)
                    ->where('warehouse_id', $locked->warehouse_id)->lockForUpdate()->first();
                $balance ??= InventoryBalance::query()->create([
                    'product_service_id' => $product->id, 'warehouse_id' => $locked->warehouse_id, 'updated_by' => $userId,
                ]);
                $unitCost = $this->acceptedUnitCost($line);
                $quantityAfter = bcadd($balance->quantity_on_hand, $line->accepted_quantity, 4);
                $valueBefore = bcmul($balance->quantity_on_hand, $balance->weighted_average_cost, 4);
                $receiptValue = bcmul($line->accepted_quantity, $unitCost, 4);
                $averageAfter = bcdiv(bcadd($valueBefore, $receiptValue, 4), $quantityAfter, 4);

                $line->inventoryMovements()->create([
                    'product_service_id' => $product->id, 'warehouse_id' => $locked->warehouse_id,
                    'type' => InventoryMovementType::PurchaseReceipt, 'movement_date' => $locked->receiving_date,
                    'quantity' => $line->accepted_quantity, 'unit_cost' => $unitCost, 'total_cost' => $receiptValue,
                    'balance_quantity_before' => $balance->quantity_on_hand, 'balance_average_cost_before' => $balance->weighted_average_cost,
                    'balance_quantity_after' => $quantityAfter, 'balance_average_cost_after' => $averageAfter,
                    'status' => InventoryMovementStatus::Posted, 'posted_at' => now(), 'posted_by' => $userId, 'created_by' => $userId,
                ]);
                $balance->update(['quantity_on_hand' => $quantityAfter, 'weighted_average_cost' => $averageAfter, 'updated_by' => $userId]);
            }
        }, 3);
    }

    public function reverse(ReceivingRecord $record, int $userId): void
    {
        DB::transaction(function () use ($record, $userId): void {
            $locked = ReceivingRecord::query()->with('lines')->lockForUpdate()->findOrFail($record->id);
            foreach ($locked->lines as $line) {
                $movement = InventoryMovement::query()->where('receiving_record_line_id', $line->id)
                    ->whereNull('reversal_of_id')->lockForUpdate()->first();
                if (! $movement) {
                    continue;
                }
                if (InventoryMovement::query()->where('reversal_of_id', $movement->id)->exists()) {
                    throw ValidationException::withMessages(['status' => "Receiving line {$line->line_number} has already been reversed."]);
                }
                $balance = InventoryBalance::query()->where('product_service_id', $movement->product_service_id)
                    ->where('warehouse_id', $movement->warehouse_id)->lockForUpdate()->firstOrFail();
                InventoryWorkflow::assertStockAvailable($balance->quantity_on_hand, $movement->quantity);
                [$quantityAfter, $averageAfter] = $this->balanceAfterReversal($balance, $movement);

                InventoryMovement::query()->create([
                    'reversal_of_id' => $movement->id,
                    'product_service_id' => $movement->product_service_id, 'warehouse_id' => $movement->warehouse_id,
                    'type' => InventoryMovementType::SupplierReturn, 'movement_date' => now()->toDateString(),
                    'quantity' => bcmul($movement->quantity, '-1', 4), 'unit_cost' => $movement->unit_cost,
                    'total_cost' => bcmul($movement->total_cost, '-1', 4),
                    'balance_quantity_before' => $balance->quantity_on_hand, 'balance_average_cost_before' => $balance->weighted_average_cost,
                    'balance_quantity_after' => $quantityAfter, 'balance_average_cost_after' => $averageAfter,
                    'status' => InventoryMovementStatus::Posted, 'posted_at' => now(), 'posted_by' => $userId, 'created_by' => $userId,
                ]);
                $balance->update(['quantity_on_hand' => $quantityAfter, 'weighted_average_cost' => $averageAfter, 'updated_by' => $userId]);
            }
        }, 3);
    }

    private function acceptedUnitCost(ReceivingRecordLine $line): string
    {
        $invoiceCost = SupplierInvoiceLine::query()->whereBelongsTo($line, 'receivingRecordLine')
            ->whereHas('supplierInvoice', fn ($query) => $query->whereIn('status', [
                SupplierInvoiceStatus::Posted, SupplierInvoiceStatus::PartiallyPaid,
                SupplierInvoiceStatus::Paid, SupplierInvoiceStatus::Overdue,
            ]))->latest('id')->value('unit_cost');

        return $invoiceCost !== null ? (string) $invoiceCost : $line->purchaseOrderLine->unit_cost;
    }

    /** @return array{numeric-string, numeric-string} */
    private function balanceAfterReversal(InventoryBalance $balance, InventoryMovement $movement): array
    {
        if ($movement->balance_quantity_after === $balance->quantity_on_hand
            && $movement->balance_average_cost_after === $balance->weighted_average_cost) {
            return [$movement->balance_quantity_before, $movement->balance_average_cost_before];
        }
        $quantity = bcsub($balance->quantity_on_hand, $movement->quantity, 4);
        if (bccomp($quantity, '0', 4) === 0) {
            return ['0.0000', '0.0000'];
        }
        $remainingValue = bcsub(bcmul($balance->quantity_on_hand, $balance->weighted_average_cost, 4), $movement->total_cost, 4);
        if (bccomp($remainingValue, '0', 4) < 0) {
            throw ValidationException::withMessages(['status' => 'The receipt cannot be reversed because the remaining inventory value would be negative.']);
        }

        return [$quantity, bcdiv($remainingValue, $quantity, 4)];
    }
}
