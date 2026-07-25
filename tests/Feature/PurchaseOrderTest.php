<?php

use App\Enums\PurchaseOrderStatus;
use App\Enums\PurchaseRequestStatus;
use App\Models\BusinessProfile;
use App\Models\CanvassQuotation;
use App\Models\Category;
use App\Models\DocumentSequence;
use App\Models\FiscalYear;
use App\Models\InventoryMovement;
use App\Models\ProductService;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequest;
use App\Models\Supplier;
use App\Models\SupplierInvoice;
use App\Models\UnitOfMeasure;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(LazilyRefreshDatabase::class);
beforeEach(fn () => $this->seed(RolesAndPermissionsSeeder::class));

function purchaseOrderFixtures(): array
{
    $admin = User::factory()->administrator()->create();
    $unit = UnitOfMeasure::factory()->for($admin, 'creator')->for($admin, 'updater')->create(['code' => 'PC', 'name' => 'Piece']);
    $category = Category::factory()->for($admin, 'creator')->for($admin, 'updater')->create(['type' => 'product']);
    $product = ProductService::factory()->for($category)->for($unit, 'unitOfMeasure')->for($admin, 'creator')->for($admin, 'updater')->create(['sku' => 'PC-1', 'name' => 'Computer', 'type' => 'product']);
    $supplierA = Supplier::factory()->for($admin, 'creator')->for($admin, 'updater')->create(['name' => 'Supplier A', 'tin' => '123-456-789']);
    $supplierB = Supplier::factory()->for($admin, 'creator')->for($admin, 'updater')->create(['name' => 'Supplier B']);

    return compact('admin', 'product', 'supplierA', 'supplierB');
}

function purchaseOrderData(array $f, array $changes = []): array
{
    return array_replace([
        'supplier_id' => $f['supplierA']->id, 'order_date' => '2026-07-18', 'expected_delivery_date' => '2026-07-25', 'delivery_location' => 'Main office', 'supplier_quotation_reference' => 'SQ-1', 'payment_terms' => '30 days', 'notes' => 'Test order', 'document_discount_rate' => '5.000000', 'freight' => '100.0000', 'other_charges' => '50.0000',
        'lines' => [
            ['product_service_id' => $f['product']->id, 'description' => 'Computer snapshot', 'ordered_quantity' => '2.0000', 'unit_cost' => '1000.0000', 'discount_rate' => '10.000000'],
            ['description' => 'Free text cable', 'uom_code' => 'M', 'uom_name' => 'Meter', 'ordered_quantity' => '10.0000', 'unit_cost' => '25.0000', 'discount_rate' => '0.000000'],
        ],
    ], $changes);
}

function createDirectPurchaseOrder($test): array
{
    $f = purchaseOrderFixtures();
    $test->actingAs($f['admin'])->post(route('purchase-orders.store'), purchaseOrderData($f))->assertRedirect();

    return $f + ['order' => PurchaseOrder::query()->with('lines')->sole()];
}

function configurePurchaseOrderSequence(array $f): void
{
    $business = BusinessProfile::factory()->active()->create();
    $year = FiscalYear::factory()->for($business)->for($f['admin'], 'creator')->create(['starts_on' => '2026-01-01', 'ends_on' => '2026-12-31']);
    DocumentSequence::query()->create(['business_profile_id' => $business->id, 'fiscal_year_id' => $year->id, 'fiscal_year_scope' => $year->id, 'document_type' => 'purchase_order', 'prefix' => 'PO-{YYYY}-', 'suffix' => '', 'current_number' => 0, 'padding' => 6, 'reset_rule' => 'fiscal_year', 'active' => true, 'created_by' => $f['admin']->id, 'updated_by' => $f['admin']->id]);
}

function approvedRequestForOrder(array $f): PurchaseRequest
{
    $request = PurchaseRequest::query()->create(['request_number' => 'PR-1', 'request_date' => '2026-07-18', 'requested_by' => $f['admin']->id, 'purpose' => 'Approved need', 'estimated_total' => '2000.0000', 'status' => PurchaseRequestStatus::Approved, 'approved_at' => now(), 'approved_by' => $f['admin']->id, 'created_by' => $f['admin']->id, 'updated_by' => $f['admin']->id]);
    $request->lines()->create(['product_service_id' => $f['product']->id, 'line_number' => 1, 'item_type' => 'product', 'sku' => 'PC-1', 'description' => 'Request snapshot', 'uom_code' => 'PC', 'uom_name' => 'Piece', 'quantity' => '2.0000', 'estimated_unit_cost' => '1000.0000', 'estimated_total' => '2000.0000']);

    return $request;
}

test('direct purchase orders calculate decimal totals and quantity balances server-side', function () {
    $f = createDirectPurchaseOrder($this);
    $order = $f['order'];
    expect($order->status)->toBe(PurchaseOrderStatus::Draft)->and($order->purchase_request_id)->toBeNull()->and($order->subtotal)->toBe('2250.0000')->and($order->line_discount_total)->toBe('200.0000')->and($order->document_discount_amount)->toBe('102.5000')->and($order->grand_total)->toBe('2097.5000');
    $line = $order->lines->first();
    expect($line->remaining_to_receive)->toBe('2.0000')->and($line->remaining_to_bill)->toBe('2.0000');
    $line->update(['received_quantity' => '1.0000', 'billed_quantity' => '0.5000']);
    expect($line->fresh()->remaining_to_receive)->toBe('1.0000')->and($line->fresh()->remaining_to_bill)->toBe('1.5000');
});

test('purchase order validation rejects invalid delivery dates and empty lines', function () {
    $f = purchaseOrderFixtures();
    $this->actingAs($f['admin'])->post(route('purchase-orders.store'), purchaseOrderData($f, ['expected_delivery_date' => '2026-01-01', 'lines' => []]))->assertSessionHasErrors(['expected_delivery_date', 'lines']);
});

test('approved requests convert once using the selected canvass and preserve source snapshots', function () {
    $f = purchaseOrderFixtures();
    $request = approvedRequestForOrder($f);
    $quote = CanvassQuotation::query()->create(['purchase_request_id' => $request->id, 'supplier_id' => $f['supplierB']->id, 'supplier_name' => 'Supplier B snapshot', 'quoted_amount' => '1900.0000', 'quotation_date' => '2026-07-18', 'payment_terms' => '15 days', 'selected' => true, 'created_by' => $f['admin']->id, 'updated_by' => $f['admin']->id]);
    $data = ['order_date' => '2026-07-18', 'delivery_location' => 'Project site', 'document_discount_rate' => '0.000000', 'freight' => '0.0000', 'other_charges' => '0.0000'];
    $this->actingAs($f['admin'])->post(route('purchase-requests.convert-to-order', $request), $data)->assertRedirect();
    $order = PurchaseOrder::query()->with('lines')->sole();
    expect($order->purchase_request_id)->toBe($request->id)->and($order->canvass_quotation_id)->toBe($quote->id)->and($order->supplier_id)->toBe($f['supplierB']->id)->and($order->lines->first()->purchase_request_line_id)->not->toBeNull()->and($request->fresh()->status)->toBe(PurchaseRequestStatus::Converted);
    $this->post(route('purchase-requests.convert-to-order', $request), $data)->assertSessionHasErrors('purchase_request');
    expect(PurchaseOrder::count())->toBe(1);
});

test('approval numbers and locks the order then issued and cancellation controls apply', function () {
    $f = createDirectPurchaseOrder($this);
    configurePurchaseOrderSequence($f);
    $order = $f['order'];
    $this->actingAs($f['admin'])->patch(route('purchase-orders.transition', $order), ['status' => 'approved'])->assertSessionHasNoErrors();
    expect($order->fresh()->purchase_order_number)->toBe('PO-2026-000001');
    $this->get(route('purchase-orders.edit', $order))->assertForbidden();
    $this->patch(route('purchase-orders.transition', $order), ['status' => 'issued'])->assertSessionHasNoErrors();
    $this->patch(route('purchase-orders.transition', $order), ['status' => 'cancelled'])->assertSessionHasErrors('reason');
    $this->patch(route('purchase-orders.transition', $order), ['status' => 'cancelled', 'reason' => 'Supplier cannot deliver'])->assertSessionHasNoErrors();
    expect($order->fresh()->cancellation_reason)->toBe('Supplier cannot deliver');
});

test('purchase order access is authorized and printable', function () {
    $f = createDirectPurchaseOrder($this);
    $viewer = User::factory()->create();
    $viewer->assignRole('Viewer');
    $this->actingAs($viewer)->get(route('purchase-orders.index'))->assertSuccessful();
    $this->get(route('purchase-orders.print', $f['order']))->assertSuccessful()->assertSee('Computer snapshot');
    $this->post(route('purchase-orders.store'), purchaseOrderData($f))->assertForbidden();
});

test('purchase orders create no receiving payable stock or journal effects', function () {
    createDirectPurchaseOrder($this);
    expect(Schema::hasTable('receivings'))->toBeFalse()->and(SupplierInvoice::query()->count())->toBe(0)->and(InventoryMovement::count())->toBe(0)->and(Schema::hasTable('journal_entries'))->toBeFalse();
});
