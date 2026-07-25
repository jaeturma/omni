<?php

use App\Enums\InventoryMovementType;
use App\Models\InventoryBalance;
use App\Models\InventoryMovement;
use App\Models\ProductService;
use App\Models\User;
use App\Models\Warehouse;
use App\Support\InventoryCostRebuilder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

uses(LazilyRefreshDatabase::class);

test('validation rebuild matches live product warehouse balances without writing history', function () {
    $user = User::factory()->create();
    $product = ProductService::factory()->create(['type' => 'product', 'is_inventory' => true]);
    $warehouse = Warehouse::factory()->create();
    $opening = costingMovement($product->id, $warehouse->id, $user->id, [
        'type' => InventoryMovementType::OpeningBalance, 'quantity' => '10.0000',
        'unit_cost' => '100.0000', 'total_cost' => '1000.0000',
    ]);
    $receipt = costingMovement($product->id, $warehouse->id, $user->id, [
        'type' => InventoryMovementType::PurchaseReceipt, 'quantity' => '10.0000',
        'unit_cost' => '200.0000', 'total_cost' => '2000.0000',
    ]);
    $issue = costingMovement($product->id, $warehouse->id, $user->id, [
        'type' => InventoryMovementType::SalesIssue, 'quantity' => '-5.0000',
        'unit_cost' => '150.0000', 'total_cost' => '-750.0000',
    ]);
    costingMovement($product->id, $warehouse->id, $user->id, [
        'reversal_of_id' => $issue->id, 'type' => InventoryMovementType::CustomerReturn,
        'quantity' => '5.0000', 'unit_cost' => '150.0000', 'total_cost' => '750.0000',
    ]);
    costingMovement($product->id, $warehouse->id, $user->id, [
        'reversal_of_id' => $receipt->id, 'type' => InventoryMovementType::SupplierReturn,
        'quantity' => '-10.0000', 'unit_cost' => '200.0000', 'total_cost' => '-2000.0000',
    ]);
    InventoryBalance::query()->create([
        'product_service_id' => $product->id, 'warehouse_id' => $warehouse->id,
        'quantity_on_hand' => '10.0000', 'weighted_average_cost' => '100.0000', 'updated_by' => $user->id,
    ]);

    $beforeCount = InventoryMovement::query()->count();
    $result = app(InventoryCostRebuilder::class)->validate($product->id, $warehouse->id);

    expect($result)->toBe([
        'quantity' => '10.0000', 'average_cost' => '100.0000',
        'live_quantity' => '10.0000', 'live_average_cost' => '100.0000', 'matches' => true,
    ])->and(InventoryMovement::query()->count())->toBe($beforeCount)
        ->and($opening->fresh()->unit_cost)->toBe('100.0000');
});

test('validation rebuild reports a mismatch without mutating the live balance', function () {
    $user = User::factory()->create();
    $product = ProductService::factory()->create(['type' => 'product', 'is_inventory' => true]);
    $warehouse = Warehouse::factory()->create();
    costingMovement($product->id, $warehouse->id, $user->id, [
        'type' => InventoryMovementType::OpeningBalance, 'quantity' => '5.0000',
        'unit_cost' => '80.0000', 'total_cost' => '400.0000',
    ]);
    $balance = InventoryBalance::query()->create([
        'product_service_id' => $product->id, 'warehouse_id' => $warehouse->id,
        'quantity_on_hand' => '4.0000', 'weighted_average_cost' => '90.0000', 'updated_by' => $user->id,
    ]);

    $result = app(InventoryCostRebuilder::class)->validate($product->id, $warehouse->id);

    expect($result['matches'])->toBeFalse()
        ->and($result['quantity'])->toBe('5.0000')
        ->and($result['average_cost'])->toBe('80.0000')
        ->and($balance->fresh()->quantity_on_hand)->toBe('4.0000')
        ->and($balance->fresh()->weighted_average_cost)->toBe('90.0000');
});

test('posted issue costs remain immutable', function () {
    $user = User::factory()->create();
    $product = ProductService::factory()->create(['type' => 'product', 'is_inventory' => true]);
    $warehouse = Warehouse::factory()->create();
    $issue = costingMovement($product->id, $warehouse->id, $user->id, [
        'type' => InventoryMovementType::SalesIssue, 'quantity' => '-1.0000',
        'unit_cost' => '125.5000', 'total_cost' => '-125.5000',
    ]);

    expect(fn () => $issue->update(['unit_cost' => '999.0000']))
        ->toThrow(LogicException::class, 'append-only')
        ->and($issue->fresh()->unit_cost)->toBe('125.5000');
});

/** @param array<string, mixed> $attributes */
function costingMovement(int $productId, int $warehouseId, int $userId, array $attributes): InventoryMovement
{
    return InventoryMovement::query()->create($attributes + [
        'product_service_id' => $productId, 'warehouse_id' => $warehouseId,
        'movement_date' => '2026-07-01', 'status' => 'posted',
        'posted_at' => now(), 'posted_by' => $userId, 'created_by' => $userId,
    ]);
}
