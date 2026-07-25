<?php

namespace App\Actions;

use App\Enums\InventoryMovementStatus;
use App\Enums\InventoryMovementType;
use App\Models\Delivery;
use App\Models\InventoryBalance;
use App\Models\InventoryMovement;
use App\Models\ProductService;
use App\Support\InventoryWorkflow;
use App\Support\WeightedAverageCosting;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PostSalesDeliveryInventory
{
    public function __construct(private WeightedAverageCosting $costing) {}

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
                $cost = $this->costing->outbound($balance->quantity_on_hand, $balance->weighted_average_cost, $line->delivered_quantity);

                $line->inventoryMovements()->create([
                    'product_service_id' => $product->id, 'warehouse_id' => $locked->warehouse_id,
                    'type' => InventoryMovementType::SalesIssue, 'movement_date' => $locked->delivery_date,
                    'quantity' => bcmul($line->delivered_quantity, '-1', 4), 'unit_cost' => $cost['issue_unit_cost'],
                    'total_cost' => bcmul($cost['movement_value'], '-1', 4), 'balance_quantity_before' => $balance->quantity_on_hand,
                    'balance_average_cost_before' => $balance->weighted_average_cost, 'balance_quantity_after' => $cost['quantity'],
                    'balance_average_cost_after' => $cost['average_cost'], 'status' => InventoryMovementStatus::Posted,
                    'posted_at' => now(), 'posted_by' => $userId, 'created_by' => $userId,
                ]);
                $balance->update(['quantity_on_hand' => $cost['quantity'], 'weighted_average_cost' => $cost['average_cost'], 'updated_by' => $userId]);
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
                $cost = $this->balanceAfterReversal($balance, $movement);

                InventoryMovement::query()->create([
                    'reversal_of_id' => $movement->id, 'product_service_id' => $movement->product_service_id,
                    'warehouse_id' => $movement->warehouse_id, 'type' => InventoryMovementType::CustomerReturn,
                    'movement_date' => now()->toDateString(), 'quantity' => bcmul($movement->quantity, '-1', 4),
                    'unit_cost' => $movement->unit_cost, 'total_cost' => bcmul($movement->total_cost, '-1', 4),
                    'balance_quantity_before' => $balance->quantity_on_hand, 'balance_average_cost_before' => $balance->weighted_average_cost,
                    'balance_quantity_after' => $cost['quantity'], 'balance_average_cost_after' => $cost['average_cost'],
                    'status' => InventoryMovementStatus::Posted, 'posted_at' => now(), 'posted_by' => $userId, 'created_by' => $userId,
                ]);
                $balance->update(['quantity_on_hand' => $cost['quantity'], 'weighted_average_cost' => $cost['average_cost'], 'updated_by' => $userId]);
            }
        }, 3);
    }

    /** @return array{quantity: numeric-string, average_cost: numeric-string, movement_value?: numeric-string} */
    private function balanceAfterReversal(InventoryBalance $balance, InventoryMovement $movement): array
    {
        if ($movement->balance_quantity_after === $balance->quantity_on_hand
            && $movement->balance_average_cost_after === $balance->weighted_average_cost) {
            return ['quantity' => $movement->balance_quantity_before, 'average_cost' => $movement->balance_average_cost_before];
        }
        $returnedQuantity = bcmul($movement->quantity, '-1', 4);

        return $this->costing->inbound(
            $balance->quantity_on_hand, $balance->weighted_average_cost, $returnedQuantity, $movement->unit_cost
        );
    }
}
