<?php

namespace App\Support;

use DomainException;

final class WeightedAverageCosting
{
    /** @return array{quantity: numeric-string, average_cost: numeric-string, movement_value: numeric-string} */
    public function inbound(string $existingQuantity, string $existingAverageCost, string $incomingQuantity, string $incomingUnitCost): array
    {
        $this->assertNonNegative($existingQuantity, 'Existing quantity');
        $this->assertNonNegative($existingAverageCost, 'Existing average cost');
        $this->assertPositive($incomingQuantity, 'Incoming quantity');
        $this->assertNonNegative($incomingUnitCost, 'Incoming unit cost');

        $newQuantity = bcadd($existingQuantity, $incomingQuantity, InventoryWorkflow::QUANTITY_SCALE);
        $incomingValue = $this->value($incomingQuantity, $incomingUnitCost);
        $newValue = bcadd($this->value($existingQuantity, $existingAverageCost), $incomingValue, InventoryWorkflow::COST_SCALE);

        return [
            'quantity' => $newQuantity,
            'average_cost' => bcdiv($newValue, $newQuantity, InventoryWorkflow::COST_SCALE),
            'movement_value' => $incomingValue,
        ];
    }

    /** @return array{quantity: numeric-string, average_cost: numeric-string, issue_unit_cost: numeric-string, movement_value: numeric-string} */
    public function outbound(string $existingQuantity, string $existingAverageCost, string $outgoingQuantity): array
    {
        $this->assertNonNegative($existingQuantity, 'Existing quantity');
        $this->assertNonNegative($existingAverageCost, 'Existing average cost');
        $this->assertPositive($outgoingQuantity, 'Outgoing quantity');
        InventoryWorkflow::assertStockAvailable($existingQuantity, $outgoingQuantity);

        $newQuantity = bcsub($existingQuantity, $outgoingQuantity, InventoryWorkflow::QUANTITY_SCALE);
        $issueValue = $this->value($outgoingQuantity, $existingAverageCost);

        return [
            'quantity' => $newQuantity,
            'average_cost' => bccomp($newQuantity, '0', InventoryWorkflow::QUANTITY_SCALE) === 0 ? '0.0000' : $existingAverageCost,
            'issue_unit_cost' => $existingAverageCost,
            'movement_value' => $issueValue,
        ];
    }

    /** @return array{quantity: numeric-string, average_cost: numeric-string, movement_value: numeric-string} */
    public function removeInbound(string $existingQuantity, string $existingAverageCost, string $removedQuantity, string $removedUnitCost): array
    {
        $this->assertNonNegative($existingQuantity, 'Existing quantity');
        $this->assertNonNegative($existingAverageCost, 'Existing average cost');
        $this->assertPositive($removedQuantity, 'Removed quantity');
        $this->assertNonNegative($removedUnitCost, 'Removed unit cost');
        InventoryWorkflow::assertStockAvailable($existingQuantity, $removedQuantity);

        $newQuantity = bcsub($existingQuantity, $removedQuantity, InventoryWorkflow::QUANTITY_SCALE);
        $removedValue = $this->value($removedQuantity, $removedUnitCost);
        if (bccomp($newQuantity, '0', InventoryWorkflow::QUANTITY_SCALE) === 0) {
            return ['quantity' => '0.0000', 'average_cost' => '0.0000', 'movement_value' => $removedValue];
        }
        $remainingValue = bcsub($this->value($existingQuantity, $existingAverageCost), $removedValue, InventoryWorkflow::COST_SCALE);
        if (bccomp($remainingValue, '0', InventoryWorkflow::COST_SCALE) < 0) {
            throw new DomainException('The movement would leave a negative inventory value.');
        }

        return [
            'quantity' => $newQuantity,
            'average_cost' => bcdiv($remainingValue, $newQuantity, InventoryWorkflow::COST_SCALE),
            'movement_value' => $removedValue,
        ];
    }

    public function value(string $quantity, string $unitCost): string
    {
        return bcmul($quantity, $unitCost, InventoryWorkflow::COST_SCALE);
    }

    private function assertPositive(string $value, string $label): void
    {
        if (bccomp($value, '0', InventoryWorkflow::QUANTITY_SCALE) !== 1) {
            throw new DomainException("$label must be greater than zero.");
        }
    }

    private function assertNonNegative(string $value, string $label): void
    {
        if (bccomp($value, '0', InventoryWorkflow::COST_SCALE) < 0) {
            throw new DomainException("$label cannot be negative.");
        }
    }
}
