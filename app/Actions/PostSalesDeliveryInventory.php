<?php

namespace App\Actions;

use App\Enums\InventoryMovementStatus;
use App\Enums\InventoryMovementType;
use App\Models\Delivery;
use App\Models\InventoryBalance;
use App\Models\InventoryMovement;
use App\Models\ProductService;
use App\Support\InventoryWorkflow;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PostSalesDeliveryInventory
{
    public function post(Delivery $delivery, int $userId): void
    {
        DB::transaction(function () use ($delivery, $userId): void {
            $locked = Delivery::query()->with('lines.salesOrderLine')->lockForUpdate()->findOrFail($delivery->id);
            foreach ($locked->lines as $line) {
                $productId = $line->salesOrderLine->product_service_id;
                $product = $productId ? ProductService::query()->lockForUpdate()->find($productId) : null;
                if (! $product || ! InventoryWorkflow::tracks($product)) {
                    continue;
                }
                if (! $locked->warehouse_id) {
                    throw ValidationException::withMessages(['warehouse_id' => 'An inventory delivery requires a warehouse.']);
                }
                if (InventoryMovement::query()->where('delivery_line_id', $line->id)->whereNull('reversal_of_id')->exists()) {
                    throw ValidationException::withMessages(['status' => "Delivery line {$line->line_number} has already been posted to inventory."]);
                }
                $balance = InventoryBalance::query()->where('product_service_id', $product->id)
                    ->where('warehouse_id', $locked->warehouse_id)->lockForUpdate()->first();
                if (! $balance) {
                    throw ValidationException::withMessages(['status' => "{$product->name} has no stock in the selected warehouse."]);
                }
                try {
                    InventoryWorkflow::assertStockAvailable($balance->quantity_on_hand, $line->delivered_quantity);
                } catch (DomainException $exception) {
                    throw ValidationException::withMessages(['status' => $exception->getMessage()]);
                }
                $quantityAfter = bcsub($balance->quantity_on_hand, $line->delivered_quantity, 4);
                $issueValue = bcmul($line->delivered_quantity, $balance->weighted_average_cost, 4);

                $line->inventoryMovements()->create([
                    'product_service_id' => $product->id, 'warehouse_id' => $locked->warehouse_id,
                    'type' => InventoryMovementType::SalesIssue, 'movement_date' => $locked->delivery_date,
                    'quantity' => bcmul($line->delivered_quantity, '-1', 4), 'unit_cost' => $balance->weighted_average_cost,
                    'total_cost' => bcmul($issueValue, '-1', 4), 'balance_quantity_before' => $balance->quantity_on_hand,
                    'balance_average_cost_before' => $balance->weighted_average_cost, 'balance_quantity_after' => $quantityAfter,
                    'balance_average_cost_after' => $balance->weighted_average_cost, 'status' => InventoryMovementStatus::Posted,
                    'posted_at' => now(), 'posted_by' => $userId, 'created_by' => $userId,
                ]);
                $balance->update(['quantity_on_hand' => $quantityAfter, 'updated_by' => $userId]);
            }
        }, 3);
    }

    public function reverse(Delivery $delivery, int $userId): void
    {
        DB::transaction(function () use ($delivery, $userId): void {
            $locked = Delivery::query()->with('lines')->lockForUpdate()->findOrFail($delivery->id);
            foreach ($locked->lines as $line) {
                $movement = InventoryMovement::query()->where('delivery_line_id', $line->id)
                    ->whereNull('reversal_of_id')->lockForUpdate()->first();
                if (! $movement) {
                    continue;
                }
                if (InventoryMovement::query()->where('reversal_of_id', $movement->id)->exists()) {
                    throw ValidationException::withMessages(['status' => "Delivery line {$line->line_number} has already been reversed."]);
                }
                $balance = InventoryBalance::query()->where('product_service_id', $movement->product_service_id)
                    ->where('warehouse_id', $movement->warehouse_id)->lockForUpdate()->firstOrFail();
                [$quantityAfter, $averageAfter] = $this->balanceAfterReversal($balance, $movement);

                InventoryMovement::query()->create([
                    'reversal_of_id' => $movement->id, 'product_service_id' => $movement->product_service_id,
                    'warehouse_id' => $movement->warehouse_id, 'type' => InventoryMovementType::CustomerReturn,
                    'movement_date' => now()->toDateString(), 'quantity' => bcmul($movement->quantity, '-1', 4),
                    'unit_cost' => $movement->unit_cost, 'total_cost' => bcmul($movement->total_cost, '-1', 4),
                    'balance_quantity_before' => $balance->quantity_on_hand, 'balance_average_cost_before' => $balance->weighted_average_cost,
                    'balance_quantity_after' => $quantityAfter, 'balance_average_cost_after' => $averageAfter,
                    'status' => InventoryMovementStatus::Posted, 'posted_at' => now(), 'posted_by' => $userId, 'created_by' => $userId,
                ]);
                $balance->update(['quantity_on_hand' => $quantityAfter, 'weighted_average_cost' => $averageAfter, 'updated_by' => $userId]);
            }
        }, 3);
    }

    /** @return array{numeric-string, numeric-string} */
    private function balanceAfterReversal(InventoryBalance $balance, InventoryMovement $movement): array
    {
        if ($movement->balance_quantity_after === $balance->quantity_on_hand
            && $movement->balance_average_cost_after === $balance->weighted_average_cost) {
            return [$movement->balance_quantity_before, $movement->balance_average_cost_before];
        }
        $returnedQuantity = bcmul($movement->quantity, '-1', 4);
        $quantity = bcadd($balance->quantity_on_hand, $returnedQuantity, 4);
        $value = bcadd(bcmul($balance->quantity_on_hand, $balance->weighted_average_cost, 4), bcmul($returnedQuantity, $movement->unit_cost, 4), 4);

        return [$quantity, bcdiv($value, $quantity, 4)];
    }
}
