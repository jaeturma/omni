<?php

use App\Enums\DeliveryStatus;
use App\Enums\SalesOrderStatus;
use App\Models\BusinessProfile;
use App\Models\Delivery;
use App\Models\DocumentSequence;
use App\Models\FiscalYear;
use App\Models\SalesInvoice;
use App\Models\SalesOrder;
use App\Models\SalesOrderLine;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(LazilyRefreshDatabase::class);
beforeEach(fn () => $this->seed(RolesAndPermissionsSeeder::class));
function deliveryFx(): array
{
    $u = User::factory()->administrator()->create();
    $o = SalesOrder::factory()->create(['status' => SalesOrderStatus::Confirmed, 'created_by' => $u->id, 'updated_by' => $u->id]);
    $line = SalesOrderLine::factory()->for($o)->create(['ordered_quantity' => '10.0000']);
    $b = BusinessProfile::factory()->active()->create();
    $y = FiscalYear::factory()->for($b)->for($u, 'creator')->create(['starts_on' => '2026-05-01', 'ends_on' => '2026-12-31']);
    DocumentSequence::create(['business_profile_id' => $b->id, 'fiscal_year_id' => $y->id, 'fiscal_year_scope' => $y->id, 'document_type' => 'delivery_receipt', 'prefix' => 'DR-{YYYY}-', 'suffix' => '', 'current_number' => 0, 'padding' => 6, 'reset_rule' => 'fiscal_year', 'active' => true, 'created_by' => $u->id, 'updated_by' => $u->id]);

    return compact('u', 'o', 'line');
}
function deliveryData($f, string $q = '4.0000'): array
{
    return ['sales_order_id' => $f['o']->id, 'delivery_date' => '2026-07-16', 'delivery_address' => 'Delivery snapshot', 'recipient_name' => 'Receiver', 'inspection_reference' => 'IAR-1', 'lines' => [['sales_order_line_id' => $f['line']->id, 'delivered_quantity' => $q]]];
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
    expect($d->fresh()->status)->toBe(DeliveryStatus::Accepted)->and(Schema::hasTable('inventory_movements'))->toBeFalse()->and(Schema::hasTable('journal_entries'))->toBeFalse()->and(Schema::hasTable('sales_invoices'))->toBeTrue()->and(SalesInvoice::count())->toBe(0);
});
