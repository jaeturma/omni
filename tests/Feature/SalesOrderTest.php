<?php

use App\Enums\QuotationStatus;
use App\Enums\SalesOrderStatus;
use App\Models\BusinessProfile;
use App\Models\Category;
use App\Models\Customer;
use App\Models\DocumentSequence;
use App\Models\FiscalYear;
use App\Models\ProductService;
use App\Models\Quotation;
use App\Models\QuotationLine;
use App\Models\SalesInvoice;
use App\Models\SalesOrder;
use App\Models\UnitOfMeasure;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(LazilyRefreshDatabase::class);
beforeEach(fn () => $this->seed(RolesAndPermissionsSeeder::class));
function orderFx(): array
{
    $u = User::factory()->administrator()->create();
    $c = Customer::factory()->for($u, 'creator')->for($u, 'updater')->create();
    $unit = UnitOfMeasure::factory()->for($u, 'creator')->for($u, 'updater')->create();
    $cat = Category::factory()->for($u, 'creator')->for($u, 'updater')->create(['type' => 'product']);
    $item = ProductService::factory()->for($cat)->for($unit, 'unitOfMeasure')->for($u, 'creator')->for($u, 'updater')->create();

    return compact('u', 'c', 'unit', 'cat', 'item');
}
function orderData($f): array
{
    return ['customer_id' => $f['c']->id, 'order_date' => '2026-07-16', 'promised_delivery_date' => '2026-07-30', 'customer_po_number' => 'PO-100', 'payment_terms' => 30, 'billing_address' => 'Bill snapshot', 'delivery_address' => 'Deliver snapshot', 'notes' => 'Note', 'document_discount_rate' => '5.000000', 'lines' => [['product_service_id' => $f['item']->id, 'description' => 'Order item', 'ordered_quantity' => '10.0000', 'unit_price' => '100.0000', 'discount_rate' => '10.000000']]];
}
function orderSequence($f): void
{
    $b = BusinessProfile::factory()->active()->create();
    $y = FiscalYear::factory()->for($b)->for($f['u'], 'creator')->create(['starts_on' => '2026-05-01', 'ends_on' => '2026-12-31']);
    DocumentSequence::create(['business_profile_id' => $b->id, 'fiscal_year_id' => $y->id, 'fiscal_year_scope' => $y->id, 'document_type' => 'sales_order', 'prefix' => 'SO-{YYYY}-', 'suffix' => '', 'current_number' => 0, 'padding' => 6, 'reset_rule' => 'fiscal_year', 'active' => true, 'created_by' => $f['u']->id, 'updated_by' => $f['u']->id]);
}
test('direct sales orders calculate totals and confirm with controlled numbering', function () {
    $f = orderFx();
    $this->actingAs($f['u'])->post(route('sales-orders.store'), orderData($f))->assertRedirect();
    $o = SalesOrder::with('lines')->sole();
    expect($o->grand_total)->toBe('855.0000')->and($o->quotation_id)->toBeNull();
    orderSequence($f);
    $this->actingAs($f['u'])->patch(route('sales-orders.transition', $o), ['status' => 'confirmed'])->assertSessionHasNoErrors();
    expect($o->fresh()->sales_order_number)->toBe('SO-2026-000001')->and($o->fresh()->status)->toBe(SalesOrderStatus::Confirmed);
    $this->actingAs($f['u'])->put(route('sales-orders.update', $o), orderData($f))->assertForbidden();
});
test('approved quotations convert once with source and snapshots preserved', function () {
    $f = orderFx();
    $q = Quotation::factory()->for($f['c'])->create(['status' => QuotationStatus::Approved, 'customer_name' => 'Quoted Customer', 'created_by' => $f['u']->id, 'updated_by' => $f['u']->id]);
    $ql = QuotationLine::factory()->for($q)->for($f['item'])->create(['quantity' => '4.0000']);
    $this->actingAs($f['u'])->post(route('quotations.convert', $q))->assertRedirect();
    $o = SalesOrder::with('lines')->sole();
    expect($o->quotation_id)->toBe($q->id)->and($o->customer_name)->toBe('Quoted Customer')->and($o->lines->first()->quotation_line_id)->toBe($ql->id)->and($q->fresh()->status)->toBe(QuotationStatus::Converted);
    $this->actingAs($f['u'])->post(route('quotations.convert', $q))->assertSessionHasErrors('quotation');
    expect(SalesOrder::count())->toBe(1);
});
test('remaining quantities and partial fulfillment are reliable', function () {
    $f = orderFx();
    $this->actingAs($f['u'])->post(route('sales-orders.store'), orderData($f));
    $o = SalesOrder::with('lines')->sole();
    orderSequence($f);
    $this->actingAs($f['u'])->patch(route('sales-orders.transition', $o), ['status' => 'confirmed']);
    $line = $o->lines->first();
    $this->actingAs($f['u'])->patch(route('sales-orders.fulfill', $o), ['quantities' => [$line->id => ['delivered_quantity' => '4.0000', 'invoiced_quantity' => '2.0000', 'cancelled_quantity' => '1.0000']]])->assertSessionHasNoErrors();
    expect($line->fresh()->remaining_quantity)->toBe('5.0000')->and($o->fresh()->status)->toBe(SalesOrderStatus::PartiallyFulfilled);
    $this->actingAs($f['u'])->patch(route('sales-orders.fulfill', $o), ['quantities' => [$line->id => ['delivered_quantity' => '11.0000', 'invoiced_quantity' => '2.0000', 'cancelled_quantity' => '0.0000']]])->assertSessionHasErrors();
});
test('authorization and cancellation reason are enforced without downstream effects', function () {
    $f = orderFx();
    $this->actingAs($f['u'])->post(route('sales-orders.store'), orderData($f));
    $o = SalesOrder::sole();
    $viewer = User::factory()->create();
    $viewer->assignRole('Viewer');
    $this->actingAs($viewer)->get(route('sales-orders.index'))->assertSuccessful();
    $this->actingAs($viewer)->post(route('sales-orders.store'), orderData($f))->assertForbidden();
    $this->actingAs($f['u'])->patch(route('sales-orders.transition', $o), ['status' => 'cancelled'])->assertSessionHasErrors('reason');
    $this->actingAs($f['u'])->patch(route('sales-orders.transition', $o), ['status' => 'cancelled', 'reason' => 'Customer cancelled'])->assertSessionHasNoErrors();
    expect($o->fresh()->cancellation_reason)->toBe('Customer cancelled')->and(Schema::hasTable('inventory_movements'))->toBeFalse()->and(Schema::hasTable('sales_invoices'))->toBeTrue()->and(SalesInvoice::count())->toBe(0)->and(Schema::hasTable('journal_entries'))->toBeFalse();
});
