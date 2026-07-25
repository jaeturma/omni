<?php

use App\Enums\InventoryMovementStatus;
use App\Enums\InventoryOpeningStatus;
use App\Models\BusinessProfile;
use App\Models\DocumentSequence;
use App\Models\FiscalPeriod;
use App\Models\FiscalYear;
use App\Models\InventoryBalance;
use App\Models\InventoryMovement;
use App\Models\InventoryOpeningBalance;
use App\Models\ProductService;
use App\Models\User;
use App\Models\Warehouse;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

uses(LazilyRefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->admin = User::factory()->administrator()->create();
    $business = BusinessProfile::factory()->active()->create();
    $year = FiscalYear::factory()->create(['business_profile_id' => $business->id, 'is_current' => true]);
    $this->period = FiscalPeriod::factory()->create([
        'fiscal_year_id' => $year->id, 'name' => 'July 2026', 'starts_on' => '2026-07-01',
        'ends_on' => '2026-07-31', 'calendar_month' => 7, 'calendar_quarter' => 3,
    ]);
    $this->warehouse = Warehouse::factory()->create();
    $this->product = ProductService::factory()->create(['type' => 'product', 'is_inventory' => true]);
    DocumentSequence::query()->create([
        'business_profile_id' => $business->id, 'fiscal_year_id' => $year->id, 'fiscal_year_scope' => $year->id,
        'document_type' => 'inventory_opening_balance', 'prefix' => 'IOB-{YYYY}-', 'current_number' => 0,
        'padding' => 6, 'reset_rule' => 'fiscal_year', 'active' => true,
        'created_by' => $this->admin->id, 'updated_by' => $this->admin->id,
    ]);
});

function inventoryOpeningPayload(object $test, array $changes = []): array
{
    return array_replace_recursive([
        'opening_date' => '2026-07-01', 'fiscal_period_id' => $test->period->id,
        'warehouse_id' => $test->warehouse->id, 'reference' => 'COUNT-001', 'notes' => 'Verified physical count',
        'lines' => [['product_service_id' => $test->product->id, 'quantity' => '12.5000', 'unit_cost' => '125.1234']],
    ], $changes);
}

function createInventoryOpening(object $test, array $changes = []): InventoryOpeningBalance
{
    $test->actingAs($test->admin)->post(route('inventory-opening-balances.store'), inventoryOpeningPayload($test, $changes))->assertRedirect();

    return InventoryOpeningBalance::query()->latest('id')->firstOrFail();
}

test('valid opening balance calculates cost and initializes quantity and average cost', function () {
    $opening = createInventoryOpening($this);
    expect($opening->lines->sole()->total_cost)->toBe('1564.0425');

    $this->patch(route('inventory-opening-balances.transition', $opening), ['status' => 'posted'])->assertRedirect();

    $balance = InventoryBalance::query()->sole();
    expect($opening->fresh()->status)->toBe(InventoryOpeningStatus::Posted)
        ->and($opening->fresh()->batch_number)->toStartWith('IOB-2026-')
        ->and($balance->quantity_on_hand)->toBe('12.5000')
        ->and($balance->weighted_average_cost)->toBe('125.1234')
        ->and(InventoryMovement::query()->sole()->status)->toBe(InventoryMovementStatus::Posted);
});

test('duplicate product warehouse opening is blocked during posting', function () {
    $first = createInventoryOpening($this);
    $this->patch(route('inventory-opening-balances.transition', $first), ['status' => 'posted'])->assertRedirect();
    $second = createInventoryOpening($this, ['reference' => 'COUNT-002']);

    $this->patch(route('inventory-opening-balances.transition', $second), ['status' => 'posted'])
        ->assertSessionHasErrors('lines');

    expect($second->fresh()->status)->toBe(InventoryOpeningStatus::Draft)
        ->and(InventoryMovement::query()->count())->toBe(1);
});

test('services non inventory products and invalid amounts are rejected', function () {
    $service = ProductService::factory()->create(['type' => 'service', 'is_inventory' => false]);
    $nonInventory = ProductService::factory()->create(['type' => 'product', 'is_inventory' => false]);

    foreach ([$service, $nonInventory] as $product) {
        $this->actingAs($this->admin)->post(route('inventory-opening-balances.store'), inventoryOpeningPayload($this, [
            'lines' => [['product_service_id' => $product->id, 'quantity' => '-1', 'unit_cost' => '-1']],
        ]))->assertSessionHasErrors(['lines.0.product_service_id', 'lines.0.quantity', 'lines.0.unit_cost']);
    }
    expect(InventoryOpeningBalance::query()->count())->toBe(0);
});

test('posted opening is immutable through the available workflow', function () {
    $opening = createInventoryOpening($this);
    $this->patch(route('inventory-opening-balances.transition', $opening), ['status' => 'posted'])->assertRedirect();

    $this->patch(route('inventory-opening-balances.transition', $opening), ['status' => 'posted'])
        ->assertSessionHasErrors('status');

    expect($opening->fresh()->lines->sole()->quantity)->toBe('12.5000');
    expect(fn () => $opening->fresh()->update(['reference' => 'Changed']))->toThrow(LogicException::class)
        ->and(fn () => $opening->fresh()->lines->sole()->update(['quantity' => '1.0000']))->toThrow(LogicException::class)
        ->and(fn () => InventoryMovement::query()->sole()->delete())->toThrow(LogicException::class);
});

test('voiding creates reversal movement and restores the empty balance', function () {
    $opening = createInventoryOpening($this);
    $this->patch(route('inventory-opening-balances.transition', $opening), ['status' => 'posted'])->assertRedirect();
    $this->patch(route('inventory-opening-balances.transition', $opening), ['status' => 'voided', 'reason' => 'Opening count corrected'])
        ->assertRedirect();

    $movements = InventoryMovement::query()->orderBy('id')->get();
    expect($opening->fresh()->status)->toBe(InventoryOpeningStatus::Voided)
        ->and($opening->fresh()->void_reason)->toBe('Opening count corrected')
        ->and($movements)->toHaveCount(2)
        ->and($movements->last()->reversal_of_id)->toBe($movements->first()->id)
        ->and($movements->last()->quantity)->toBe('-12.5000')
        ->and(InventoryBalance::query()->sole()->quantity_on_hand)->toBe('0.0000')
        ->and(InventoryBalance::query()->sole()->weighted_average_cost)->toBe('0.0000');
});

test('authorization protects viewing creating posting and voiding', function () {
    $viewer = User::factory()->create();
    $viewer->assignRole('Viewer');
    $opening = createInventoryOpening($this);

    $this->actingAs($viewer)->get(route('inventory-opening-balances.index'))->assertOk();
    $this->post(route('inventory-opening-balances.store'), inventoryOpeningPayload($this))->assertForbidden();
    $this->patch(route('inventory-opening-balances.transition', $opening), ['status' => 'posted'])->assertForbidden();
    $this->actingAs($this->admin)->patch(route('inventory-opening-balances.transition', $opening), ['status' => 'posted'])->assertRedirect();
    $this->actingAs($viewer)->patch(route('inventory-opening-balances.transition', $opening), ['status' => 'voided', 'reason' => 'Denied'])->assertForbidden();
});
