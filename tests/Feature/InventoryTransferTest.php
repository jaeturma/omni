<?php

use App\Enums\InventoryMovementType;
use App\Enums\InventoryTransferStatus;
use App\Models\BusinessProfile;
use App\Models\DocumentSequence;
use App\Models\FiscalPeriod;
use App\Models\FiscalYear;
use App\Models\InventoryBalance;
use App\Models\InventoryMovement;
use App\Models\InventoryTransfer;
use App\Models\ProductService;
use App\Models\User;
use App\Models\Warehouse;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(LazilyRefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->admin = User::factory()->administrator()->create();
    $business = BusinessProfile::factory()->active()->create();
    $year = FiscalYear::factory()->create([
        'business_profile_id' => $business->id,
        'is_current' => true,
        'starts_on' => '2026-01-01',
        'ends_on' => '2026-12-31',
    ]);
    $this->period = FiscalPeriod::factory()->create([
        'fiscal_year_id' => $year->id,
        'name' => 'July 2026',
        'starts_on' => '2026-07-01',
        'ends_on' => '2026-07-31',
        'calendar_month' => 7,
        'calendar_quarter' => 3,
        'status' => 'open',
    ]);
    $this->source = Warehouse::factory()->create(['code' => 'SRC']);
    $this->destination = Warehouse::factory()->create(['code' => 'DST']);
    $this->product = ProductService::factory()->create(['type' => 'product', 'is_inventory' => true]);
    DocumentSequence::query()->create([
        'business_profile_id' => $business->id,
        'fiscal_year_id' => $year->id,
        'fiscal_year_scope' => $year->id,
        'document_type' => 'inventory_transfer',
        'prefix' => 'ITR-{YYYY}-',
        'current_number' => 0,
        'padding' => 6,
        'reset_rule' => 'fiscal_year',
        'active' => true,
        'created_by' => $this->admin->id,
        'updated_by' => $this->admin->id,
    ]);
});

function transferPayload(object $test, array $changes = []): array
{
    return array_replace_recursive([
        'transfer_date' => '2026-07-10',
        'fiscal_period_id' => $test->period->id,
        'source_warehouse_id' => $test->source->id,
        'destination_warehouse_id' => $test->destination->id,
        'reference' => 'REQ-100',
        'notes' => 'Move stock to branch.',
        'lines' => [[
            'product_service_id' => $test->product->id,
            'quantity' => '3.0000',
        ]],
    ], $changes);
}

function createTransfer(object $test, array $changes = []): InventoryTransfer
{
    $test->actingAs($test->admin)
        ->post(route('inventory-transfers.store'), transferPayload($test, $changes))
        ->assertRedirect();

    return InventoryTransfer::query()->latest('id')->firstOrFail();
}

function transitionTransfer(object $test, InventoryTransfer $transfer, string $status, ?string $reason = null): void
{
    $test->actingAs($test->admin)
        ->patch(route('inventory-transfers.transition', $transfer), array_filter([
            'status' => $status,
            'reason' => $reason,
        ]))
        ->assertSessionHasNoErrors();
}

test('same warehouse and invalid lines are rejected', function () {
    $service = ProductService::factory()->create(['type' => 'service', 'is_inventory' => false]);

    $this->actingAs($this->admin)->post(route('inventory-transfers.store'), transferPayload($this, [
        'destination_warehouse_id' => $this->source->id,
        'lines' => [[
            'product_service_id' => $service->id,
            'quantity' => '0.0000',
        ]],
    ]))->assertSessionHasErrors([
        'source_warehouse_id',
        'lines.0.product_service_id',
        'lines.0.quantity',
    ]);

    expect(InventoryTransfer::query()->count())->toBe(0);
});

test('release blocks unavailable stock without partial movements', function () {
    InventoryBalance::query()->create([
        'product_service_id' => $this->product->id,
        'warehouse_id' => $this->source->id,
        'quantity_on_hand' => '2.0000',
        'weighted_average_cost' => '125.5000',
        'updated_by' => $this->admin->id,
    ]);
    $transfer = createTransfer($this);
    transitionTransfer($this, $transfer, 'approved');

    $this->patch(route('inventory-transfers.transition', $transfer), ['status' => 'released'])
        ->assertSessionHasErrors('lines');

    expect($transfer->fresh()->status)->toBe(InventoryTransferStatus::Approved)
        ->and(InventoryMovement::query()->count())->toBe(0)
        ->and(InventoryBalance::query()->sole()->quantity_on_hand)->toBe('2.0000');
});

test('multi-line release rolls back every movement when a later line is unavailable', function () {
    $secondProduct = ProductService::factory()->create(['type' => 'product', 'is_inventory' => true]);
    InventoryBalance::query()->create([
        'product_service_id' => $this->product->id,
        'warehouse_id' => $this->source->id,
        'quantity_on_hand' => '10.0000',
        'weighted_average_cost' => '100.0000',
        'updated_by' => $this->admin->id,
    ]);
    InventoryBalance::query()->create([
        'product_service_id' => $secondProduct->id,
        'warehouse_id' => $this->source->id,
        'quantity_on_hand' => '1.0000',
        'weighted_average_cost' => '50.0000',
        'updated_by' => $this->admin->id,
    ]);
    $transfer = createTransfer($this, ['lines' => [
        ['product_service_id' => $this->product->id, 'quantity' => '3.0000'],
        ['product_service_id' => $secondProduct->id, 'quantity' => '2.0000'],
    ]]);
    transitionTransfer($this, $transfer, 'approved');

    $this->patch(route('inventory-transfers.transition', $transfer), ['status' => 'released'])
        ->assertSessionHasErrors('lines');

    expect(InventoryMovement::query()->count())->toBe(0)
        ->and(InventoryBalance::query()->where('product_service_id', $this->product->id)->sole()->quantity_on_hand)->toBe('10.0000')
        ->and(InventoryBalance::query()->where('product_service_id', $secondProduct->id)->sole()->quantity_on_hand)->toBe('1.0000')
        ->and($transfer->fresh()->status)->toBe(InventoryTransferStatus::Approved);
});

test('release and receipt create linked two-sided movements with preserved source cost', function () {
    InventoryBalance::query()->create([
        'product_service_id' => $this->product->id,
        'warehouse_id' => $this->source->id,
        'quantity_on_hand' => '10.0000',
        'weighted_average_cost' => '125.5000',
        'updated_by' => $this->admin->id,
    ]);
    InventoryBalance::query()->create([
        'product_service_id' => $this->product->id,
        'warehouse_id' => $this->destination->id,
        'quantity_on_hand' => '2.0000',
        'weighted_average_cost' => '100.0000',
        'updated_by' => $this->admin->id,
    ]);
    $transfer = createTransfer($this);
    transitionTransfer($this, $transfer, 'approved');
    transitionTransfer($this, $transfer, 'released');

    $line = $transfer->fresh()->lines->sole();
    $out = InventoryMovement::query()->sole();
    expect($transfer->fresh()->transfer_number)->toStartWith('ITR-2026-')
        ->and($line->source_unit_cost)->toBe('125.5000')
        ->and($line->total_cost)->toBe('376.5000')
        ->and($out->type)->toBe(InventoryMovementType::TransferOut)
        ->and($out->inventory_transfer_line_id)->toBe($line->id)
        ->and(InventoryBalance::query()->where('warehouse_id', $this->source->id)->sole()->quantity_on_hand)->toBe('7.0000');

    transitionTransfer($this, $transfer, 'in_transit');
    expect($transfer->fresh()->status)->toBe(InventoryTransferStatus::InTransit)
        ->and($transfer->fresh()->lines->sole()->quantity)->toBe('3.0000');

    transitionTransfer($this, $transfer, 'received');
    $movements = InventoryMovement::query()->orderBy('id')->get();
    $destination = InventoryBalance::query()->where('warehouse_id', $this->destination->id)->sole();
    expect($movements)->toHaveCount(2)
        ->and($movements->last()->type)->toBe(InventoryMovementType::TransferIn)
        ->and($movements->last()->inventory_transfer_line_id)->toBe($line->id)
        ->and($movements->last()->unit_cost)->toBe('125.5000')
        ->and($destination->quantity_on_hand)->toBe('5.0000')
        ->and($destination->weighted_average_cost)->toBe('115.3000');
});

test('voiding a received transfer creates linked reversals and restores both warehouses', function () {
    InventoryBalance::query()->create([
        'product_service_id' => $this->product->id,
        'warehouse_id' => $this->source->id,
        'quantity_on_hand' => '10.0000',
        'weighted_average_cost' => '125.5000',
        'updated_by' => $this->admin->id,
    ]);
    $transfer = createTransfer($this);
    transitionTransfer($this, $transfer, 'approved');
    transitionTransfer($this, $transfer, 'released');
    transitionTransfer($this, $transfer, 'in_transit');
    transitionTransfer($this, $transfer, 'received');
    transitionTransfer($this, $transfer, 'voided', 'Transfer cancelled after receipt.');

    $movements = InventoryMovement::query()->orderBy('id')->get();
    expect($transfer->fresh()->status)->toBe(InventoryTransferStatus::Voided)
        ->and($transfer->fresh()->void_reason)->toBe('Transfer cancelled after receipt.')
        ->and($movements)->toHaveCount(4)
        ->and($movements[2]->reversal_of_id)->toBe($movements[1]->id)
        ->and($movements[3]->reversal_of_id)->toBe($movements[0]->id)
        ->and(InventoryBalance::query()->where('warehouse_id', $this->source->id)->sole()->quantity_on_hand)->toBe('10.0000')
        ->and(InventoryBalance::query()->where('warehouse_id', $this->destination->id)->sole()->quantity_on_hand)->toBe('0.0000');
});

test('voiding a released in-transit transfer restores source stock', function () {
    InventoryBalance::query()->create([
        'product_service_id' => $this->product->id,
        'warehouse_id' => $this->source->id,
        'quantity_on_hand' => '5.0000',
        'weighted_average_cost' => '80.0000',
        'updated_by' => $this->admin->id,
    ]);
    $transfer = createTransfer($this);
    transitionTransfer($this, $transfer, 'approved');
    transitionTransfer($this, $transfer, 'released');
    transitionTransfer($this, $transfer, 'in_transit');
    transitionTransfer($this, $transfer, 'voided', 'Shipment returned before receipt.');

    expect(InventoryMovement::query()->count())->toBe(2)
        ->and(InventoryBalance::query()->sole()->quantity_on_hand)->toBe('5.0000')
        ->and($transfer->fresh()->status)->toBe(InventoryTransferStatus::Voided);
});

test('authorization separates view create approve release receive and void abilities', function () {
    $viewer = User::factory()->create();
    $viewer->assignRole('Viewer');
    $transfer = createTransfer($this);

    $this->actingAs($viewer)->get(route('inventory-transfers.index'))->assertOk();
    $this->post(route('inventory-transfers.store'), transferPayload($this))->assertForbidden();
    $this->patch(route('inventory-transfers.transition', $transfer), ['status' => 'approved'])->assertForbidden();

    transitionTransfer($this, $transfer, 'approved');
    $this->actingAs($viewer)->patch(route('inventory-transfers.transition', $transfer), ['status' => 'released'])->assertForbidden();
    $this->actingAs($viewer)->patch(route('inventory-transfers.transition', $transfer), ['status' => 'voided', 'reason' => 'Denied'])->assertForbidden();
});

test('warehouse transfers do not create accounting records', function () {
    expect(Schema::hasTable('journal_entries'))->toBeFalse();
});
