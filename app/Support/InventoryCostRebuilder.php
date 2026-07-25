<?php

namespace App\Support;

use App\Models\InventoryBalance;
use App\Models\InventoryMovement;

final class InventoryCostRebuilder
{
    public function __construct(private WeightedAverageCosting $costing) {}

    /**
     * This validation-only rebuild never writes balances or posted movements.
     *
     * @return array{quantity: numeric-string, average_cost: numeric-string, live_quantity: numeric-string, live_average_cost: numeric-string, matches: bool}
     */
    public function validate(int $productId, int $warehouseId): array
    {
        $quantity = '0.0000';
        $averageCost = '0.0000';
        $movements = InventoryMovement::query()->with('reversalOf:id,quantity')
            ->where('product_service_id', $productId)->where('warehouse_id', $warehouseId)
            ->orderBy('movement_date')->orderBy('id')->get();

        foreach ($movements as $movement) {
            if (bccomp($movement->quantity, '0', InventoryWorkflow::QUANTITY_SCALE) === 1) {
                $result = $this->costing->inbound($quantity, $averageCost, $movement->quantity, $movement->unit_cost);
            } else {
                $outgoingQuantity = bcmul($movement->quantity, '-1', InventoryWorkflow::QUANTITY_SCALE);
                $result = $movement->reversalOf && bccomp($movement->reversalOf->quantity, '0', InventoryWorkflow::QUANTITY_SCALE) === 1
                    ? $this->costing->removeInbound($quantity, $averageCost, $outgoingQuantity, $movement->unit_cost)
                    : $this->costing->outbound($quantity, $averageCost, $outgoingQuantity);
            }
            $quantity = $result['quantity'];
            $averageCost = $result['average_cost'];
        }

        $balance = InventoryBalance::query()->where('product_service_id', $productId)
            ->where('warehouse_id', $warehouseId)->firstOrNew();
        $matches = bccomp($quantity, $balance->quantity_on_hand, InventoryWorkflow::QUANTITY_SCALE) === 0
            && bccomp($averageCost, $balance->weighted_average_cost, InventoryWorkflow::COST_SCALE) === 0;

        return [
            'quantity' => $quantity, 'average_cost' => $averageCost,
            'live_quantity' => $balance->quantity_on_hand, 'live_average_cost' => $balance->weighted_average_cost,
            'matches' => $matches,
        ];
    }
}
