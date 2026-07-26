<?php

use App\Actions\PostSalesDeliveryInventory;
use App\Enums\DeliveryStatus;
use App\Enums\SalesOrderStatus;
use App\Models\BusinessProfile;
use App\Models\Delivery;
use App\Models\DocumentSequence;
use App\Models\FiscalYear;
use App\Models\InventoryBalance;
use App\Models\InventoryMovement;
use App\Models\JournalEntry;
use App\Models\SalesInvoice;
use App\Models\SalesOrder;
use App\Models\SalesOrderLine;
use App\Models\User;
use App\Models\Warehouse;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(LazilyRefreshDatabase::class);
beforeEach(fn () => $this->seed(RolesAndPermissionsSeeder::class));
function deliveryFx(): array
{
    $u = User::factory()->administrator()->create();
    $o = SalesOrder::factory()->create(['status' => SalesOrderStatus::Confirmed, 'created_by' => $u->id, 'updated_by' => $u->id]);
    $line = SalesOrderLine::factory()->for($o)->create(['ordered_quantity' => '10.0000']);
    $product = $line->productService;
    $warehouse = Warehouse::factory()->create(['created_by' => $u->id, 'updated_by' => $u->id]);
    InventoryBalance::query()->create(['product_service_id' => $product->id, 'warehouse_id' => $warehouse->id,
        'quantity_on_hand' => '20.0000', 'weighted_average_cost' => '500.0000', 'updated_by' => $u->id]);
    $b = BusinessProfile::factory()->active()->create();
    $y = FiscalYear::factory()->for($b)->for($u, 'creator')->create(['starts_on' => '2026-05-01', 'ends_on' => '2026-12-31']);
    DocumentSequence::create(['business_profile_id' => $b->id, 'fiscal_year_id' => $y->id, 'fiscal_year_scope' => $y->id, 'document_type' => 'delivery_receipt', 'prefix' => 'DR-{YYYY}-', 'suffix' => '', 'current_number' => 0, 'padding' => 6, 'reset_rule' => 'fiscal_year', 'active' => true, 'created_by' => $u->id, 'updated_by' => $u->id]);

    return compact('u', 'o', 'line', 'product', 'warehouse');
}
function deliveryData($f, string $q = '4.0000'): array
{
    return ['sales_order_id' => $f['o']->id, 'warehouse_id' => $f['warehouse']->id, 'delivery_date' => '2026-07-16', 'delivery_address' => 'Delivery snapshot', 'recipient_name' => 'Receiver', 'inspection_reference' => 'IAR-1', 'lines' => [['sales_order_line_id' => $f['line']->id, 'delivered_quantity' => $q]]];
}
test('partial and full deliveries reconcile transactionally to the order', function () {
    $f = deliveryFx();
    $this->actingAs($f['u'])->post(route('deliveries.store'), deliveryData($f))->assertRedirect();
    $d = Delivery::sole();
    expect($f['line']->fresh()->delivered_quantity)->toBe('0.0000');
    $this->actingAs($f['u'])->patch(route('deliveries.transition', $d), ['status' => 'released'])->assertSessionHasNoErrors();
    expect($d->fresh()->delivery_number)->toBe('DR-2026-000001')->and($f['line']->fresh()->delivered_quantity)->toBe('4.0000')->and($f['line']->fresh()->remaining_quantity)->toBe('6.0000')->and($f['o']->fresh()->status)->toBe(SalesOrderStatus::PartiallyFulfilled);
    $this->actingAs($f['u'])->post(route('deliveries.store'), deliveryData($f, '6.0000'));
    $d2 = Delivery::latest('id')->first();
    $this->actingAs($f['u'])->patch(route('deliveries.transition', $d2), ['status' => 'released'])->assertSessionHasNoErrors();
    expect($f['line']->fresh()->remaining_quantity)->toBe('0.0000')->and($f['o']->fresh()->status)->toBe(SalesOrderStatus::Fulfilled);
});
test('over delivery is blocked under the release transaction', function () {
    $f = deliveryFx();
    $this->actingAs($f['u'])->post(route('deliveries.store'), deliveryData($f, '11.0000'));
    $d = Delivery::sole();
    $this->actingAs($f['u'])->patch(route('deliveries.transition', $d), ['status' => 'released'])->assertSessionHasErrors('lines');
    expect($d->fresh()->status)->toBe(DeliveryStatus::Draft)->and($f['line']->fresh()->delivered_quantity)->toBe('0.0000');
});
test('cancellation reverses released fulfillment quantities safely', function () {
    $f = deliveryFx();
    $this->actingAs($f['u'])->post(route('deliveries.store'), deliveryData($f));
    $d = Delivery::sole();
    $this->actingAs($f['u'])->patch(route('deliveries.transition', $d), ['status' => 'released']);
    $this->actingAs($f['u'])->patch(route('deliveries.transition', $d), ['status' => 'cancelled'])->assertSessionHasErrors('reason');
    $this->actingAs($f['u'])->patch(route('deliveries.transition', $d), ['status' => 'cancelled', 'reason' => 'Delivery recalled'])->assertSessionHasNoErrors();
    expect($f['line']->fresh()->delivered_quantity)->toBe('0.0000')->and($f['o']->fresh()->status)->toBe(SalesOrderStatus::Confirmed)->and($d->fresh()->cancellation_reason)->toBe('Delivery recalled');
});
test('delivery lifecycle authorization print and prohibited effects are enforced', function () {
    $f = deliveryFx();
    $this->actingAs($f['u'])->post(route('deliveries.store'), deliveryData($f));
    $d = Delivery::sole();
    $viewer = User::factory()->create();
    $viewer->assignRole('Viewer');
    $this->actingAs($viewer)->get(route('deliveries.index'))->assertSuccessful();
    $this->actingAs($viewer)->get(route('deliveries.print', $d))->assertSuccessful();
    $this->actingAs($viewer)->patch(route('deliveries.transition', $d), ['status' => 'released'])->assertForbidden();
    $this->actingAs($f['u'])->patch(route('deliveries.transition', $d), ['status' => 'released']);
    $this->actingAs($f['u'])->patch(route('deliveries.transition', $d), ['status' => 'delivered', 'received_by_name' => 'Juan', 'received_at' => '2026-07-16 10:00'])->assertSessionHasNoErrors();
    $this->actingAs($f['u'])->patch(route('deliveries.transition', $d), ['status' => 'accepted', 'acceptance_notes' => 'Complete'])->assertSessionHasNoErrors();
    expect($d->fresh()->status)->toBe(DeliveryStatus::Accepted)->and(InventoryMovement::count())->toBe(1)->and(JournalEntry::query()->count())->toBe(0)->and(Schema::hasTable('sales_invoices'))->toBeTrue()->and(SalesInvoice::count())->toBe(0);
});

test('delivered quantities issue stock once and preserve source and cost', function () {
    $f = deliveryFx();
    $this->actingAs($f['u'])->post(route('deliveries.store'), deliveryData($f, '4.0000'));
    $delivery = Delivery::sole();
    $this->patch(route('deliveries.transition', $delivery), ['status' => 'released']);
    $this->patch(route('deliveries.transition', $delivery), [
        'status' => 'delivered', 'received_by_name' => 'Juan', 'received_at' => '2026-07-16 10:00',
    ])->assertSessionHasNoErrors();

    $movement = InventoryMovement::query()->sole();
    expect($movement->delivery_line_id)->toBe($delivery->lines()->sole()->id)
        ->and($movement->quantity)->toBe('-4.0000')
        ->and($movement->unit_cost)->toBe('500.0000')
        ->and($movement->total_cost)->toBe('-2000.0000')
        ->and(InventoryBalance::query()->sole()->quantity_on_hand)->toBe('16.0000')
        ->and(InventoryBalance::query()->sole()->weighted_average_cost)->toBe('500.0000');

    expect(fn () => app(PostSalesDeliveryInventory::class)->post($delivery, $f['u']->id))
        ->toThrow(ValidationException::class);
    expect(InventoryMovement::query()->count())->toBe(1);
});

test('partial issues and negative stock prevention are transactional', function () {
    $f = deliveryFx();
    InventoryBalance::query()->sole()->update(['quantity_on_hand' => '3.0000']);
    $this->actingAs($f['u'])->post(route('deliveries.store'), deliveryData($f, '4.0000'));
    $delivery = Delivery::sole();
    $this->patch(route('deliveries.transition', $delivery), ['status' => 'released']);
    $this->patch(route('deliveries.transition', $delivery), [
        'status' => 'delivered', 'received_by_name' => 'Juan', 'received_at' => '2026-07-16 10:00',
    ])->assertSessionHasErrors('status');

    expect($delivery->fresh()->status)->toBe(DeliveryStatus::Released)
        ->and(InventoryBalance::query()->sole()->quantity_on_hand)->toBe('3.0000')
        ->and(InventoryMovement::query()->count())->toBe(0);
});

test('services and non inventory products do not create delivery movements', function () {
    $f = deliveryFx();
    foreach ([['type' => 'service', 'is_inventory' => false], ['type' => 'product', 'is_inventory' => false]] as $attributes) {
        $f['product']->update($attributes);
        $this->actingAs($f['u'])->post(route('deliveries.store'), deliveryData($f, '1.0000'));
        $delivery = Delivery::query()->latest('id')->firstOrFail();
        $this->patch(route('deliveries.transition', $delivery), ['status' => 'released']);
        $this->patch(route('deliveries.transition', $delivery), [
            'status' => 'delivered', 'received_by_name' => 'Juan', 'received_at' => '2026-07-16 10:00',
        ])->assertSessionHasNoErrors();
    }
    expect(InventoryMovement::query()->count())->toBe(0)
        ->and(InventoryBalance::query()->sole()->quantity_on_hand)->toBe('20.0000');
});

test('cancelling a delivered issue creates a reversal and restores stock', function () {
    $f = deliveryFx();
    $this->actingAs($f['u'])->post(route('deliveries.store'), deliveryData($f, '4.0000'));
    $delivery = Delivery::sole();
    $this->patch(route('deliveries.transition', $delivery), ['status' => 'released']);
    $this->patch(route('deliveries.transition', $delivery), [
        'status' => 'delivered', 'received_by_name' => 'Juan', 'received_at' => '2026-07-16 10:00',
    ]);
    $this->patch(route('deliveries.transition', $delivery), ['status' => 'cancelled', 'reason' => 'Customer rejected delivery'])
        ->assertSessionHasNoErrors();

    $movements = InventoryMovement::query()->orderBy('id')->get();
    expect($movements)->toHaveCount(2)
        ->and($movements->last()->reversal_of_id)->toBe($movements->first()->id)
        ->and($movements->last()->quantity)->toBe('4.0000')
        ->and(InventoryBalance::query()->sole()->quantity_on_hand)->toBe('20.0000')
        ->and(InventoryBalance::query()->sole()->weighted_average_cost)->toBe('500.0000');
});

test('inventory issue permissions are seeded and required for stock effects', function () {
    expect(Permission::query()->whereIn('name', ['inventory-issues.view', 'inventory-issues.post', 'inventory-issues.reverse'])->count())->toBe(3)
        ->and(Role::findByName('Administrator')->hasAllPermissions(['inventory-issues.view', 'inventory-issues.post', 'inventory-issues.reverse']))->toBeTrue()
        ->and(Role::findByName('Viewer')->hasPermissionTo('inventory-issues.view'))->toBeTrue()
        ->and(Role::findByName('Viewer')->hasPermissionTo('inventory-issues.post'))->toBeFalse();

    $f = deliveryFx();
    $this->actingAs($f['u'])->post(route('deliveries.store'), deliveryData($f, '1.0000'));
    $delivery = Delivery::sole();
    $this->patch(route('deliveries.transition', $delivery), ['status' => 'released']);
    $restricted = User::factory()->create();
    $restricted->givePermissionTo('deliveries.release');
    $this->actingAs($restricted)->patch(route('deliveries.transition', $delivery), [
        'status' => 'delivered', 'received_by_name' => 'Juan', 'received_at' => '2026-07-16 10:00',
    ])->assertForbidden();
    expect(InventoryMovement::query()->count())->toBe(0);
});
