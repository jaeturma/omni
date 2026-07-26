<?php

use App\Enums\InventoryMovementType;
use App\Enums\PhysicalCountStatus;
use App\Models\BusinessProfile;
use App\Models\DocumentSequence;
use App\Models\FiscalPeriod;
use App\Models\FiscalYear;
use App\Models\InventoryBalance;
use App\Models\InventoryMovement;
use App\Models\JournalEntry;
use App\Models\PhysicalCount;
use App\Models\ProductService;
use App\Models\User;
use App\Models\Warehouse;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Carbon;

uses(LazilyRefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->admin = User::factory()->administrator()->create();
    $business = BusinessProfile::factory()->active()->create();
    $year = FiscalYear::factory()->create([
        'business_profile_id' => $business->id, 'is_current' => true,
        'starts_on' => '2026-01-01', 'ends_on' => '2026-12-31',
    ]);
    $this->period = FiscalPeriod::factory()->create([
        'fiscal_year_id' => $year->id, 'name' => 'July 2026',
        'starts_on' => '2026-07-01', 'ends_on' => '2026-07-31',
        'calendar_month' => 7, 'calendar_quarter' => 3, 'status' => 'open',
    ]);
    $this->warehouse = Warehouse::factory()->create(['code' => 'MAIN']);
    $this->product = ProductService::factory()->create(['type' => 'product', 'is_inventory' => true]);
    DocumentSequence::query()->create([
        'business_profile_id' => $business->id, 'fiscal_year_id' => $year->id,
        'fiscal_year_scope' => $year->id, 'document_type' => 'inventory_physical_count',
        'prefix' => 'IPC-{YYYY}-', 'current_number' => 0, 'padding' => 6,
        'reset_rule' => 'fiscal_year', 'active' => true,
        'created_by' => $this->admin->id, 'updated_by' => $this->admin->id,
    ]);
});

function physicalCountPayload(object $test, array $changes = []): array
{
    return array_replace_recursive([
        'count_date' => '2026-07-10',
        'fiscal_period_id' => $test->period->id,
        'warehouse_id' => $test->warehouse->id,
        'blind_count' => false,
        'notes' => 'Quarterly cycle count.',
        'product_ids' => [$test->product->id],
    ], $changes);
}

function createPhysicalCount(object $test, array $changes = []): PhysicalCount
{
    $test->actingAs($test->admin)
        ->post(route('physical-counts.store'), physicalCountPayload($test, $changes))
        ->assertRedirect();

    return PhysicalCount::query()->latest('id')->firstOrFail();
}

function transitionPhysicalCount(object $test, PhysicalCount $count, string $status, ?string $reason = null): void
{
    $test->actingAs($test->admin)
        ->patch(route('physical-counts.transition', $count), array_filter([
            'status' => $status,
            'reason' => $reason,
        ]))
        ->assertSessionHasNoErrors();
}

function recordPhysicalCount(object $test, PhysicalCount $count, array $quantities): void
{
    $lines = $count->fresh()->lines->values()->map(fn ($line, int $index): array => [
        'id' => $line->id,
        'counted_quantity' => $quantities[$index],
        'explanation' => bccomp($quantities[$index], $line->system_quantity_snapshot, 4) === 0
            ? null
            : 'Verified physical variance.',
    ])->all();

    $test->actingAs($test->admin)->put(route('physical-counts.record', $count), ['lines' => $lines])
        ->assertSessionHasNoErrors();
}

function reviewAndApprovePhysicalCount(object $test, PhysicalCount $count): void
{
    $test->actingAs($test->admin)->patch(route('physical-counts.review', $count))->assertSessionHasNoErrors();
    transitionPhysicalCount($test, $count, 'approved');
}

test('creation freezes reliable quantity and cost snapshots at the server cutoff', function () {
    InventoryBalance::query()->create([
        'product_service_id' => $this->product->id, 'warehouse_id' => $this->warehouse->id,
        'quantity_on_hand' => '10.0000', 'weighted_average_cost' => '125.5000', 'updated_by' => $this->admin->id,
    ]);
    $this->travelTo(Carbon::parse('2026-07-10 09:30:00'));
    $count = createPhysicalCount($this);
    $line = $count->lines->sole();
    InventoryBalance::query()->sole()->update(['quantity_on_hand' => '7.0000', 'weighted_average_cost' => '140.0000']);

    expect($count->cutoff_at->format('Y-m-d H:i:s'))->toBe('2026-07-10 09:30:00')
        ->and($line->system_quantity_snapshot)->toBe('10.0000')
        ->and($line->unit_cost_snapshot)->toBe('125.5000')
        ->and($line->fresh()->system_quantity_snapshot)->toBe('10.0000')
        ->and($line->fresh()->unit_cost_snapshot)->toBe('125.5000');
});

test('blind count hides snapshot details while quantities are being recorded', function () {
    InventoryBalance::query()->create([
        'product_service_id' => $this->product->id, 'warehouse_id' => $this->warehouse->id,
        'quantity_on_hand' => '13.2500', 'weighted_average_cost' => '91.7500', 'updated_by' => $this->admin->id,
    ]);
    $count = createPhysicalCount($this, ['blind_count' => true]);
    transitionPhysicalCount($this, $count, 'counting');

    $this->get(route('physical-counts.show', $count))->assertOk()
        ->assertDontSee('System qty')
        ->assertDontSee('91.7500');
});

test('variance quantity and value are calculated server side', function () {
    InventoryBalance::query()->create([
        'product_service_id' => $this->product->id, 'warehouse_id' => $this->warehouse->id,
        'quantity_on_hand' => '10.0000', 'weighted_average_cost' => '125.5000', 'updated_by' => $this->admin->id,
    ]);
    $count = createPhysicalCount($this);
    transitionPhysicalCount($this, $count, 'counting');
    recordPhysicalCount($this, $count, ['8.5000']);

    $line = $count->fresh()->lines->sole();
    expect($line->counted_quantity)->toBe('8.5000')
        ->and($line->variance_quantity)->toBe('-1.5000')
        ->and($line->variance_value)->toBe('-188.2500')
        ->and($count->fresh()->counted_by)->toBe($this->admin->id);
});

test('recount before posting replaces variances and invalidates prior review and approval', function () {
    InventoryBalance::query()->create([
        'product_service_id' => $this->product->id, 'warehouse_id' => $this->warehouse->id,
        'quantity_on_hand' => '10.0000', 'weighted_average_cost' => '100.0000', 'updated_by' => $this->admin->id,
    ]);
    $count = createPhysicalCount($this);
    transitionPhysicalCount($this, $count, 'counting');
    recordPhysicalCount($this, $count, ['8.0000']);
    transitionPhysicalCount($this, $count, 'submitted');
    reviewAndApprovePhysicalCount($this, $count);

    transitionPhysicalCount($this, $count, 'counting');
    expect($count->fresh()->reviewed_by)->toBeNull()->and($count->fresh()->approved_by)->toBeNull();
    recordPhysicalCount($this, $count, ['9.0000']);
    transitionPhysicalCount($this, $count, 'submitted');

    expect($count->fresh()->lines->sole()->variance_quantity)->toBe('-1.0000')
        ->and($count->fresh()->status)->toBe(PhysicalCountStatus::Submitted);
});

test('review and approval are required before posting gains and losses', function () {
    $gainProduct = ProductService::factory()->create(['type' => 'product', 'is_inventory' => true]);
    InventoryBalance::query()->create([
        'product_service_id' => $this->product->id, 'warehouse_id' => $this->warehouse->id,
        'quantity_on_hand' => '10.0000', 'weighted_average_cost' => '100.0000', 'updated_by' => $this->admin->id,
    ]);
    InventoryBalance::query()->create([
        'product_service_id' => $gainProduct->id, 'warehouse_id' => $this->warehouse->id,
        'quantity_on_hand' => '5.0000', 'weighted_average_cost' => '50.0000', 'updated_by' => $this->admin->id,
    ]);
    $count = createPhysicalCount($this, ['product_ids' => [$this->product->id, $gainProduct->id]]);
    transitionPhysicalCount($this, $count, 'counting');
    recordPhysicalCount($this, $count, ['8.0000', '7.0000']);
    transitionPhysicalCount($this, $count, 'submitted');

    $this->patch(route('physical-counts.transition', $count), ['status' => 'approved'])
        ->assertSessionHasErrors('status');
    $this->patch(route('physical-counts.transition', $count), ['status' => 'posted'])
        ->assertSessionHasErrors('status');
    reviewAndApprovePhysicalCount($this, $count);
    transitionPhysicalCount($this, $count, 'posted');

    $movements = InventoryMovement::query()->orderBy('id')->get();
    expect($count->fresh()->count_number)->toStartWith('IPC-2026-')
        ->and($movements)->toHaveCount(2)
        ->and($movements->pluck('type')->all())->toContain(InventoryMovementType::PhysicalCountLoss, InventoryMovementType::PhysicalCountGain)
        ->and($movements->every(fn ($movement): bool => $movement->physical_count_line_id !== null))->toBeTrue()
        ->and(InventoryBalance::query()->where('product_service_id', $this->product->id)->sole()->quantity_on_hand)->toBe('8.0000')
        ->and(InventoryBalance::query()->where('product_service_id', $gainProduct->id)->sole()->quantity_on_hand)->toBe('7.0000');

    $this->patch(route('physical-counts.transition', $count), ['status' => 'posted'])
        ->assertSessionHasErrors('status');
    expect(InventoryMovement::query()->count())->toBe(2);
});

test('voiding a posted count appends reversals and preserves existing movement history', function () {
    InventoryBalance::query()->create([
        'product_service_id' => $this->product->id, 'warehouse_id' => $this->warehouse->id,
        'quantity_on_hand' => '10.0000', 'weighted_average_cost' => '100.0000', 'updated_by' => $this->admin->id,
    ]);
    $existing = InventoryMovement::query()->create([
        'product_service_id' => $this->product->id, 'warehouse_id' => $this->warehouse->id,
        'type' => InventoryMovementType::OpeningBalance, 'movement_date' => '2026-07-01',
        'quantity' => '10.0000', 'unit_cost' => '100.0000', 'total_cost' => '1000.0000',
        'status' => 'posted', 'posted_at' => now(), 'posted_by' => $this->admin->id, 'created_by' => $this->admin->id,
    ]);
    $count = createPhysicalCount($this);
    InventoryBalance::query()->sole()->update(['weighted_average_cost' => '120.0000']);
    transitionPhysicalCount($this, $count, 'counting');
    recordPhysicalCount($this, $count, ['12.0000']);
    transitionPhysicalCount($this, $count, 'submitted');
    reviewAndApprovePhysicalCount($this, $count);
    transitionPhysicalCount($this, $count, 'posted');
    transitionPhysicalCount($this, $count, 'voided', 'Count sheet was invalid.');

    $movements = InventoryMovement::query()->orderBy('id')->get();
    expect($count->fresh()->status)->toBe(PhysicalCountStatus::Voided)
        ->and($count->fresh()->void_reason)->toBe('Count sheet was invalid.')
        ->and($movements)->toHaveCount(3)
        ->and($movements->first()->id)->toBe($existing->id)
        ->and($movements->last()->reversal_of_id)->toBe($movements[1]->id)
        ->and(InventoryBalance::query()->sole()->quantity_on_hand)->toBe('10.0000')
        ->and(InventoryBalance::query()->sole()->weighted_average_cost)->toBe('120.0000');
});

test('authorization separates physical count responsibilities', function () {
    $viewer = User::factory()->create();
    $viewer->assignRole('Viewer');
    $count = createPhysicalCount($this);

    $this->actingAs($viewer)->get(route('physical-counts.index'))->assertOk();
    $this->post(route('physical-counts.store'), physicalCountPayload($this))->assertForbidden();
    $this->patch(route('physical-counts.transition', $count), ['status' => 'counting'])->assertForbidden();

    transitionPhysicalCount($this, $count, 'counting');
    $line = $count->fresh()->lines->sole();
    $this->actingAs($viewer)->put(route('physical-counts.record', $count), ['lines' => [[
        'id' => $line->id, 'counted_quantity' => '0.0000',
    ]]])->assertForbidden();
    $this->patch(route('physical-counts.review', $count))->assertForbidden();
    $this->patch(route('physical-counts.transition', $count), ['status' => 'voided', 'reason' => 'Denied'])->assertForbidden();
});

test('physical counts do not create accounting records', function () {
    expect(JournalEntry::query()->count())->toBe(0);
});
