<?php

use App\Enums\InventoryAdjustmentStatus;
use App\Enums\InventoryMovementType;
use App\Models\BusinessProfile;
use App\Models\DocumentSequence;
use App\Models\FiscalPeriod;
use App\Models\FiscalYear;
use App\Models\InventoryAdjustment;
use App\Models\InventoryAdjustmentReason;
use App\Models\InventoryBalance;
use App\Models\InventoryMovement;
use App\Models\ProductService;
use App\Models\User;
use App\Models\Warehouse;
use Database\Seeders\InventoryAdjustmentReasonSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

uses(LazilyRefreshDatabase::class);

beforeEach(function () {
    $this->seed([RolesAndPermissionsSeeder::class, InventoryAdjustmentReasonSeeder::class]);
    $this->admin = User::factory()->administrator()->create();
    $business = BusinessProfile::factory()->active()->create();
    $year = FiscalYear::factory()->create(['business_profile_id' => $business->id, 'is_current' => true]);
    $this->period = FiscalPeriod::factory()->create(['fiscal_year_id' => $year->id, 'name' => 'July 2026',
        'starts_on' => '2026-07-01', 'ends_on' => '2026-07-31', 'calendar_month' => 7, 'calendar_quarter' => 3]);
    $this->warehouse = Warehouse::factory()->create();
    $this->product = ProductService::factory()->create(['type' => 'product', 'is_inventory' => true]);
    $this->reason = InventoryAdjustmentReason::query()->where('code', 'other')->firstOrFail();
    DocumentSequence::query()->create(['business_profile_id' => $business->id, 'fiscal_year_id' => $year->id,
        'fiscal_year_scope' => $year->id, 'document_type' => 'inventory_adjustment', 'prefix' => 'IADJ-{YYYY}-',
        'current_number' => 0, 'padding' => 6, 'reset_rule' => 'fiscal_year', 'active' => true,
        'created_by' => $this->admin->id, 'updated_by' => $this->admin->id]);
});

function adjustmentPayload(object $test, string $type = 'in', array $changes = []): array
{
    return array_replace_recursive(['adjustment_date' => '2026-07-10', 'fiscal_period_id' => $test->period->id,
        'warehouse_id' => $test->warehouse->id, 'type' => $type,
        'inventory_adjustment_reason_id' => $test->reason->id, 'explanation' => 'Verified stock correction.',
        'lines' => [['product_service_id' => $test->product->id, 'quantity' => '2.0000',
            'unit_cost' => $type === 'in' ? '150.0000' : null]]], $changes);
}

function createAdjustment(object $test, string $type = 'in', array $changes = []): InventoryAdjustment
{
    $test->actingAs($test->admin)->post(route('inventory-adjustments.store'), adjustmentPayload($test, $type, $changes))->assertRedirect();

    return InventoryAdjustment::query()->latest('id')->firstOrFail();
}

function transitionAdjustment(object $test, InventoryAdjustment $adjustment, string $status, ?string $reason = null): void
{
    $test->actingAs($test->admin)->patch(route('inventory-adjustments.transition', $adjustment),
        array_filter(['status' => $status, 'reason' => $reason]))->assertRedirect();
}

test('adjustment in requires approval and updates weighted average cost', function () {
    InventoryBalance::query()->create(['product_service_id' => $this->product->id, 'warehouse_id' => $this->warehouse->id,
        'quantity_on_hand' => '10.0000', 'weighted_average_cost' => '100.0000', 'updated_by' => $this->admin->id]);
    $adjustment = createAdjustment($this);
    $this->patch(route('inventory-adjustments.transition', $adjustment), ['status' => 'posted'])->assertSessionHasErrors('status');
    transitionAdjustment($this, $adjustment, 'approved');
    transitionAdjustment($this, $adjustment, 'posted');

    $balance = InventoryBalance::query()->sole();
    expect($adjustment->fresh()->status)->toBe(InventoryAdjustmentStatus::Posted)
        ->and($adjustment->fresh()->adjustment_number)->toStartWith('IADJ-2026-')
        ->and($balance->quantity_on_hand)->toBe('12.0000')
        ->and($balance->weighted_average_cost)->toBe('108.3333')
        ->and(InventoryMovement::query()->sole()->type)->toBe(InventoryMovementType::AdjustmentIn);
});

test('adjustment out uses current average cost and prevents negative stock', function () {
    InventoryBalance::query()->create(['product_service_id' => $this->product->id, 'warehouse_id' => $this->warehouse->id,
        'quantity_on_hand' => '3.0000', 'weighted_average_cost' => '125.5000', 'updated_by' => $this->admin->id]);
    $adjustment = createAdjustment($this, 'out');
    transitionAdjustment($this, $adjustment, 'approved');
    transitionAdjustment($this, $adjustment, 'posted');
    expect($adjustment->fresh()->lines->sole()->unit_cost)->toBe('125.5000')
        ->and(InventoryBalance::query()->sole()->quantity_on_hand)->toBe('1.0000');

    $blocked = createAdjustment($this, 'out', ['lines' => [['product_service_id' => $this->product->id, 'quantity' => '2.0000', 'unit_cost' => null]]]);
    transitionAdjustment($this, $blocked, 'approved');
    $this->patch(route('inventory-adjustments.transition', $blocked), ['status' => 'posted'])->assertSessionHasErrors();
    expect($blocked->fresh()->status)->toBe(InventoryAdjustmentStatus::Approved);
});

test('reason explanation and stock-in cost are validated', function () {
    $inactive = InventoryAdjustmentReason::query()->create(['code' => 'inactive', 'name' => 'Inactive', 'active' => false]);
    $this->actingAs($this->admin)->post(route('inventory-adjustments.store'), adjustmentPayload($this, 'in', [
        'inventory_adjustment_reason_id' => $inactive->id, 'explanation' => '',
        'lines' => [['product_service_id' => $this->product->id, 'quantity' => '1.0000', 'unit_cost' => null]],
    ]))->assertSessionHasErrors(['inventory_adjustment_reason_id', 'explanation', 'lines.0.unit_cost']);
    expect(InventoryAdjustment::query()->count())->toBe(0);
});

test('voiding posts an opposite reversal and restores stock', function () {
    InventoryBalance::query()->create(['product_service_id' => $this->product->id, 'warehouse_id' => $this->warehouse->id,
        'quantity_on_hand' => '5.0000', 'weighted_average_cost' => '100.0000', 'updated_by' => $this->admin->id]);
    $adjustment = createAdjustment($this, 'out');
    transitionAdjustment($this, $adjustment, 'approved');
    transitionAdjustment($this, $adjustment, 'posted');
    transitionAdjustment($this, $adjustment, 'voided', 'Adjustment entered in error.');
    $movements = InventoryMovement::query()->orderBy('id')->get();
    expect($adjustment->fresh()->status)->toBe(InventoryAdjustmentStatus::Voided)
        ->and($movements)->toHaveCount(2)->and($movements->last()->reversal_of_id)->toBe($movements->first()->id)
        ->and($movements->last()->type)->toBe(InventoryMovementType::AdjustmentIn)
        ->and(InventoryBalance::query()->sole()->quantity_on_hand)->toBe('5.0000');
});

test('authorization separates view create approve post and void permissions', function () {
    $viewer = User::factory()->create();
    $viewer->assignRole('Viewer');
    $adjustment = createAdjustment($this);
    $this->actingAs($viewer)->get(route('inventory-adjustments.index'))->assertOk();
    $this->post(route('inventory-adjustments.store'), adjustmentPayload($this))->assertForbidden();
    $this->patch(route('inventory-adjustments.transition', $adjustment), ['status' => 'approved'])->assertForbidden();
    transitionAdjustment($this, $adjustment, 'approved');
    $this->actingAs($viewer)->patch(route('inventory-adjustments.transition', $adjustment), ['status' => 'posted'])->assertForbidden();
    transitionAdjustment($this, $adjustment, 'posted');
    $this->actingAs($viewer)->patch(route('inventory-adjustments.transition', $adjustment), ['status' => 'voided', 'reason' => 'Denied'])->assertForbidden();
});
