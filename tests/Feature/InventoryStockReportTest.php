<?php

use App\Enums\InventoryMovementType;
use App\Models\Brand;
use App\Models\FiscalPeriod;
use App\Models\InventoryMovement;
use App\Models\InventoryTransfer;
use App\Models\ProductService;
use App\Models\User;
use App\Models\Warehouse;
use App\Reports\InventoryStockReport;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(LazilyRefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->user = User::factory()->administrator()->create();
    $this->product = ProductService::factory()->create(['reorder_level' => '8.0000']);
    $this->warehouse = Warehouse::factory()->create();
});

test('stock as of date and valuation reconcile to posted movements while reversed rows are excluded', function () {
    inventoryReportMovement($this->product, $this->warehouse, $this->user, '2026-07-01', '10.0000', '100.0000', '1000.0000');
    inventoryReportMovement($this->product, $this->warehouse, $this->user, '2026-07-10', '-4.0000', '100.0000', '-400.0000');
    inventoryReportMovement($this->product, $this->warehouse, $this->user, '2026-07-12', '50.0000', '999.0000', '49950.0000', 'reversed');

    $early = app(InventoryStockReport::class)->stockRows(inventoryReportFilters(['as_of' => '2026-07-05', 'end_date' => '2026-07-05']));
    $current = app(InventoryStockReport::class)->summary(inventoryReportFilters(), true);

    expect($early)->toHaveCount(1)
        ->and($early->first()->quantity)->toBe('10.0000')
        ->and($current['as_of_quantity'])->toBe('6.0000')
        ->and($current['as_of_value'])->toBe('600.0000')
        ->and($current['closing_quantity'])->toBe('6.0000')
        ->and($current['closing_value'])->toBe('600.0000');
});

test('warehouse transfers are neutral across all warehouses and remain visible by warehouse', function () {
    $destination = Warehouse::factory()->create();
    inventoryReportMovement($this->product, $this->warehouse, $this->user, '2026-07-10', '-3.0000', '100.0000', '-300.0000', 'posted', InventoryMovementType::TransferOut);
    inventoryReportMovement($this->product, $destination, $this->user, '2026-07-10', '3.0000', '100.0000', '300.0000', 'posted', InventoryMovementType::TransferIn);

    $all = app(InventoryStockReport::class)->summary(inventoryReportFilters(), true);
    $source = app(InventoryStockReport::class)->summary(inventoryReportFilters(['warehouse_id' => $this->warehouse->id]), true);

    expect($all['movement_quantity'])->toBe('0.0000')
        ->and($all['movement_value'])->toBe('0.0000')
        ->and($source['movement_quantity'])->toBe('-3.0000');
});

test('reorder alerts use configured levels and category brand product and movement filters work', function () {
    $brand = Brand::factory()->create();
    $this->product->update(['brand_id' => $brand->id]);
    inventoryReportMovement($this->product, $this->warehouse, $this->user, '2026-07-01', '5.0000', '100.0000', '500.0000');
    $filters = inventoryReportFilters([
        'product_service_id' => $this->product->id, 'category_id' => $this->product->category_id,
        'brand_id' => $brand->id, 'warehouse_id' => $this->warehouse->id,
        'movement_type' => InventoryMovementType::PurchaseReceipt->value,
    ]);
    $report = app(InventoryStockReport::class);

    expect($report->alerts($filters))->toHaveCount(1)
        ->and($report->ledgerPaginator($filters))->toHaveCount(1);
});

test('released transfers report inventory in transit', function () {
    $destination = Warehouse::factory()->create();
    $transfer = InventoryTransfer::query()->create([
        'transfer_date' => '2026-07-10', 'fiscal_period_id' => FiscalPeriod::factory()->create()->id,
        'source_warehouse_id' => $this->warehouse->id, 'destination_warehouse_id' => $destination->id,
        'status' => 'in_transit', 'created_by' => $this->user->id, 'updated_by' => $this->user->id,
    ]);
    $transfer->lines()->create([
        'product_service_id' => $this->product->id, 'line_number' => 1, 'quantity' => '2.5000',
        'source_unit_cost' => '100.0000', 'total_cost' => '250.0000',
    ]);

    $lines = app(InventoryStockReport::class)->inTransit(inventoryReportFilters());

    expect($lines)->toHaveCount(1)->and($lines->first()->quantity)->toBe('2.5000');
});

test('report endpoints validate dates and enforce view and export authorization', function () {
    $viewer = User::factory()->create();
    $viewer->assignRole('Viewer');
    $unauthorized = User::factory()->create();

    $this->actingAs($viewer)->get(route('inventory-reports.index', inventoryReportFilters()))->assertSuccessful();
    $this->get(route('inventory-reports.print', inventoryReportFilters()))->assertSuccessful();
    $this->get(route('inventory-reports.export', inventoryReportFilters()))->assertForbidden();
    $this->actingAs($unauthorized)->get(route('inventory-reports.index', inventoryReportFilters()))->assertForbidden();
    $this->actingAs($this->user)->get(route('inventory-reports.index', inventoryReportFilters([
        'start_date' => '2026-07-20', 'end_date' => '2026-07-10',
    ])))->assertSessionHasErrors('end_date');
});

test('cost and valuation stay hidden without their dedicated permissions', function () {
    inventoryReportMovement($this->product, $this->warehouse, $this->user, '2026-07-01', '5.0000', '123.4567', '617.2835');
    $viewer = User::factory()->create();
    $viewer->givePermissionTo(['inventory-reports.view', 'inventory-reports.export']);

    $this->actingAs($viewer)->get(route('inventory-reports.index', inventoryReportFilters()))
        ->assertSuccessful()->assertDontSee('Average cost')->assertDontSee('As-of value');
    $response = $this->get(route('inventory-reports.export', inventoryReportFilters()))->assertSuccessful();
    expect($response->streamedContent())->not->toContain('Unit Cost')->not->toContain('Value');
});

test('reports create no snapshot financial statement or general ledger tables', function () {
    expect(Schema::hasTable('inventory_report_snapshots'))->toBeFalse()
        ->and(Schema::hasTable('financial_statements'))->toBeFalse()
        ->and(Schema::hasTable('general_ledgers'))->toBeFalse();
});

/** @param array<string, mixed> $changes
 * @return array<string, mixed>
 */
function inventoryReportFilters(array $changes = []): array
{
    return array_merge([
        'start_date' => '2026-07-01', 'end_date' => '2026-07-31', 'as_of' => '2026-07-31',
        'product_service_id' => null, 'category_id' => null, 'brand_id' => null,
        'warehouse_id' => null, 'movement_type' => null,
    ], $changes);
}

function inventoryReportMovement(
    ProductService $product,
    Warehouse $warehouse,
    User $user,
    string $date,
    string $quantity,
    string $unitCost,
    string $totalCost,
    string $status = 'posted',
    InventoryMovementType $type = InventoryMovementType::PurchaseReceipt,
): InventoryMovement {
    return InventoryMovement::query()->create([
        'product_service_id' => $product->id, 'warehouse_id' => $warehouse->id, 'type' => $type,
        'movement_date' => $date, 'quantity' => $quantity, 'unit_cost' => $unitCost, 'total_cost' => $totalCost,
        'balance_quantity_after' => $quantity, 'balance_average_cost_after' => $unitCost,
        'status' => $status, 'posted_at' => now(), 'posted_by' => $user->id, 'created_by' => $user->id,
    ]);
}
