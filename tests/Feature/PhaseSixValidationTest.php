<?php

use App\Enums\InventoryMovementType;
use App\Models\InventoryBalance;
use App\Models\InventoryMovement;
use App\Models\ProductService;
use App\Models\User;
use App\Models\Warehouse;
use App\Reports\InventoryStockReport;
use App\Support\InventoryCostRebuilder;
use App\Support\InventoryWorkflow;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;

uses(LazilyRefreshDatabase::class);

test('phase six quantities and values reconcile across product warehouse balances', function () {
    $user = User::factory()->create();
    $product = ProductService::factory()->create();
    $source = Warehouse::factory()->create();
    $destination = Warehouse::factory()->create();

    phaseSixMovement($product, $source, $user, InventoryMovementType::OpeningBalance, '2026-07-01', '10.0000', '100.0000', '1000.0000', '10.0000', '100.0000');
    phaseSixMovement($product, $source, $user, InventoryMovementType::PurchaseReceipt, '2026-07-02', '10.0000', '200.0000', '2000.0000', '20.0000', '150.0000');
    phaseSixMovement($product, $source, $user, InventoryMovementType::SalesIssue, '2026-07-03', '-4.0000', '150.0000', '-600.0000', '16.0000', '150.0000');
    phaseSixMovement($product, $source, $user, InventoryMovementType::TransferOut, '2026-07-04', '-3.0000', '150.0000', '-450.0000', '13.0000', '150.0000');
    phaseSixMovement($product, $destination, $user, InventoryMovementType::TransferIn, '2026-07-04', '3.0000', '150.0000', '450.0000', '3.0000', '150.0000');

    InventoryBalance::query()->create([
        'product_service_id' => $product->id, 'warehouse_id' => $source->id,
        'quantity_on_hand' => '13.0000', 'weighted_average_cost' => '150.0000', 'updated_by' => $user->id,
    ]);
    InventoryBalance::query()->create([
        'product_service_id' => $product->id, 'warehouse_id' => $destination->id,
        'quantity_on_hand' => '3.0000', 'weighted_average_cost' => '150.0000', 'updated_by' => $user->id,
    ]);

    $filters = [
        'start_date' => '2026-07-01', 'end_date' => '2026-07-31', 'as_of' => '2026-07-31',
        'product_service_id' => $product->id, 'category_id' => null, 'brand_id' => null,
        'warehouse_id' => null, 'movement_type' => null,
    ];
    $summary = app(InventoryStockReport::class)->summary($filters, true);

    expect($summary['as_of_quantity'])->toBe('16.0000')
        ->and($summary['as_of_value'])->toBe('2400.0000')
        ->and(app(InventoryCostRebuilder::class)->validate($product->id, $source->id)['matches'])->toBeTrue()
        ->and(app(InventoryCostRebuilder::class)->validate($product->id, $destination->id)['matches'])->toBeTrue();
});

test('all phase six permissions remain seeded', function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    expect(Permission::query()->whereIn('name', InventoryWorkflow::PERMISSIONS)->count())
        ->toBe(count(InventoryWorkflow::PERMISSIONS));
});

test('phase seven foundations exist while prohibited downstream tables do not', function () {
    expect(Schema::hasTable('accounts'))->toBeTrue()
        ->and(Schema::hasTable('journal_entries'))->toBeTrue()
        ->and(Schema::hasTable('journal_entry_lines'))->toBeTrue();

    foreach (['chart_of_accounts', 'general_ledgers', 'trial_balances',
        'financial_statements', 'tax_returns', 'payroll_runs', 'fixed_assets'] as $table) {
        expect(Schema::hasTable($table))->toBeFalse();
    }
});

function phaseSixMovement(
    ProductService $product,
    Warehouse $warehouse,
    User $user,
    InventoryMovementType $type,
    string $date,
    string $quantity,
    string $unitCost,
    string $totalCost,
    string $quantityAfter,
    string $averageCostAfter,
): void {
    InventoryMovement::query()->create([
        'product_service_id' => $product->id, 'warehouse_id' => $warehouse->id,
        'type' => $type, 'movement_date' => $date, 'quantity' => $quantity,
        'unit_cost' => $unitCost, 'total_cost' => $totalCost,
        'balance_quantity_after' => $quantityAfter, 'balance_average_cost_after' => $averageCostAfter,
        'status' => 'posted', 'posted_at' => now(), 'posted_by' => $user->id, 'created_by' => $user->id,
    ]);
}
