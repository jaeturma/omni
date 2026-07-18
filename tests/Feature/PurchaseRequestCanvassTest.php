<?php

use App\Enums\PurchaseRequestStatus;
use App\Models\BusinessProfile;
use App\Models\Category;
use App\Models\DocumentSequence;
use App\Models\FiscalYear;
use App\Models\ProductService;
use App\Models\PurchaseRequest;
use App\Models\Supplier;
use App\Models\UnitOfMeasure;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(LazilyRefreshDatabase::class);
beforeEach(fn () => $this->seed(RolesAndPermissionsSeeder::class));

function purchasingFixtures(): array
{
    $admin = User::factory()->administrator()->create();
    $unit = UnitOfMeasure::factory()->for($admin, 'creator')->for($admin, 'updater')->create(['code' => 'PC', 'name' => 'Piece']);
    $category = Category::factory()->for($admin, 'creator')->for($admin, 'updater')->create(['type' => 'product']);
    $product = ProductService::factory()->for($category)->for($unit, 'unitOfMeasure')->for($admin, 'creator')->for($admin, 'updater')->create(['sku' => 'LAP-1', 'name' => 'Laptop', 'type' => 'product']);
    $serviceCategory = Category::factory()->for($admin, 'creator')->for($admin, 'updater')->create(['type' => 'service']);
    $service = ProductService::factory()->for($serviceCategory, 'category')->for($unit, 'unitOfMeasure')->for($admin, 'creator')->for($admin, 'updater')->create(['sku' => 'INSTALL', 'name' => 'Installation', 'type' => 'service']);
    $supplierA = Supplier::factory()->for($admin, 'creator')->for($admin, 'updater')->create(['name' => 'Supplier A']);
    $supplierB = Supplier::factory()->for($admin, 'creator')->for($admin, 'updater')->create(['name' => 'Supplier B']);

    return compact('admin', 'product', 'service', 'supplierA', 'supplierB');
}

function purchaseRequestData(array $f, array $changes = []): array
{
    return array_replace([
        'request_date' => '2026-07-18', 'requested_by' => $f['admin']->id, 'needed_by' => '2026-07-25', 'purpose' => 'ICT laboratory setup', 'requesting_unit' => 'Operations', 'project_reference' => 'DEPED-1',
        'lines' => [
            ['product_service_id' => $f['product']->id, 'preferred_supplier_id' => $f['supplierA']->id, 'description' => 'Laptop snapshot', 'quantity' => '2.0000', 'estimated_unit_cost' => '30000.0000'],
            ['product_service_id' => $f['service']->id, 'description' => 'Installation snapshot', 'quantity' => '1.0000', 'estimated_unit_cost' => '5000.0000'],
            ['description' => 'Free text cable', 'uom_code' => 'M', 'uom_name' => 'Meter', 'quantity' => '10.0000', 'estimated_unit_cost' => '25.5000'],
        ],
    ], $changes);
}

function createPurchaseRequest($test): array
{
    $f = purchasingFixtures();
    $test->actingAs($f['admin'])->post(route('purchase-requests.store'), purchaseRequestData($f))->assertRedirect();

    return $f + ['request' => PurchaseRequest::query()->with('lines')->sole()];
}

function configurePurchaseRequestSequence(array $f): void
{
    $business = BusinessProfile::factory()->active()->create();
    $year = FiscalYear::factory()->for($business)->for($f['admin'], 'creator')->create(['starts_on' => '2026-01-01', 'ends_on' => '2026-12-31']);
    DocumentSequence::query()->create(['business_profile_id' => $business->id, 'fiscal_year_id' => $year->id, 'fiscal_year_scope' => $year->id, 'document_type' => 'purchase_request', 'prefix' => 'PR-{YYYY}-', 'suffix' => '', 'current_number' => 0, 'padding' => 6, 'reset_rule' => 'fiscal_year', 'active' => true, 'created_by' => $f['admin']->id, 'updated_by' => $f['admin']->id]);
}

test('draft requests support product service and free-text lines with server totals and snapshots', function () {
    $f = createPurchaseRequest($this);
    $request = $f['request'];
    expect($request->status)->toBe(PurchaseRequestStatus::Draft)->and($request->estimated_total)->toBe('65255.0000')->and($request->lines)->toHaveCount(3)
        ->and($request->lines[0]->item_type)->toBe('product')->and($request->lines[1]->item_type)->toBe('service')->and($request->lines[2]->item_type)->toBe('free_text')->and($request->lines[2]->uom_code)->toBe('M');
    $f['product']->update(['sku' => 'CHANGED']);
    $f['supplierA']->update(['name' => 'Changed Supplier']);
    expect($request->lines()->first()->sku)->toBe('LAP-1');
});

test('draft request validation rejects invalid dates and empty lines', function () {
    $f = purchasingFixtures();
    $this->actingAs($f['admin'])->post(route('purchase-requests.store'), purchaseRequestData($f, ['needed_by' => '2026-01-01', 'lines' => []]))->assertSessionHasErrors(['needed_by', 'lines']);
});

test('submission issues a request number and approval makes the request immutable', function () {
    $f = createPurchaseRequest($this);
    configurePurchaseRequestSequence($f);
    $request = $f['request'];
    $this->actingAs($f['admin'])->patch(route('purchase-requests.transition', $request), ['status' => 'submitted'])->assertSessionHasNoErrors();
    expect($request->fresh()->request_number)->toBe('PR-2026-000001');
    $this->patch(route('purchase-requests.transition', $request), ['status' => 'approved'])->assertSessionHasNoErrors();
    $this->get(route('purchase-requests.edit', $request))->assertForbidden();
});

test('rejection and cancellation require reasons', function () {
    $f = createPurchaseRequest($this);
    configurePurchaseRequestSequence($f);
    $request = $f['request'];
    $this->actingAs($f['admin'])->patch(route('purchase-requests.transition', $request), ['status' => 'submitted']);
    $this->patch(route('purchase-requests.transition', $request), ['status' => 'rejected'])->assertSessionHasErrors('reason');
    $this->patch(route('purchase-requests.transition', $request), ['status' => 'rejected', 'reason' => 'Budget unavailable'])->assertSessionHasNoErrors();
    expect($request->fresh()->rejection_reason)->toBe('Budget unavailable');
});

test('multiple supplier quotations preserve snapshots and only one may be selected', function () {
    $f = createPurchaseRequest($this);
    $request = $f['request'];
    $quote = fn (Supplier $supplier, string $amount, bool $selected) => ['supplier_id' => $supplier->id, 'quoted_amount' => $amount, 'quotation_date' => '2026-07-18', 'validity_date' => '2026-08-18', 'delivery_terms' => 'Seven days', 'payment_terms' => 'Thirty days', 'selected' => $selected, 'evaluation_notes' => 'Evaluated'];
    $this->actingAs($f['admin'])->post(route('purchase-requests.canvass-quotations.store', $request), $quote($f['supplierA'], '60000.0000', true))->assertSessionHasNoErrors();
    $this->post(route('purchase-requests.canvass-quotations.store', $request), $quote($f['supplierB'], '59000.0000', true))->assertSessionHasNoErrors();
    expect($request->canvassQuotations()->count())->toBe(2)->and($request->canvassQuotations()->where('selected', true)->sole()->supplier_name)->toBe('Supplier B');
    $f['supplierB']->update(['name' => 'Renamed Supplier']);
    expect($request->canvassQuotations()->where('selected', true)->sole()->supplier_name)->toBe('Supplier B');
});

test('purchase request and canvass actions are authorized', function () {
    $f = createPurchaseRequest($this);
    $viewer = User::factory()->create();
    $viewer->assignRole('Viewer');
    $this->actingAs($viewer)->get(route('purchase-requests.index'))->assertSuccessful();
    $this->post(route('purchase-requests.store'), purchaseRequestData($f))->assertForbidden();
    $this->post(route('purchase-requests.canvass-quotations.store', $f['request']), ['supplier_id' => $f['supplierA']->id])->assertForbidden();
});

test('purchase requests create no payable stock journal or purchase order effects', function () {
    createPurchaseRequest($this);
    expect(Schema::hasTable('purchase_orders'))->toBeFalse()->and(Schema::hasTable('supplier_invoices'))->toBeFalse()->and(Schema::hasTable('inventory_movements'))->toBeFalse()->and(Schema::hasTable('journal_entries'))->toBeFalse();
});
