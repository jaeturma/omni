<?php

use App\Enums\PurchaseOrderStatus;
use App\Enums\ReceivingStatus;
use App\Models\BusinessProfile;
use App\Models\Category;
use App\Models\DocumentSequence;
use App\Models\FiscalYear;
use App\Models\ProductService;
use App\Models\PurchaseOrder;
use App\Models\ReceivingRecord;
use App\Models\Supplier;
use App\Models\SupplierInvoice;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\Warehouse;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(LazilyRefreshDatabase::class);
beforeEach(fn () => $this->seed(RolesAndPermissionsSeeder::class));

function receivingFixtures(): array
{
    $admin = User::factory()->administrator()->create();
    $unit = UnitOfMeasure::factory()->for($admin, 'creator')->for($admin, 'updater')->create(['code' => 'PC', 'name' => 'Piece']);
    $category = Category::factory()->for($admin, 'creator')->for($admin, 'updater')->create(['type' => 'product']);
    $product = ProductService::factory()->for($category)->for($unit, 'unitOfMeasure')->for($admin, 'creator')->for($admin, 'updater')->create(['sku' => 'PC-1', 'name' => 'Computer']);
    $supplier = Supplier::factory()->for($admin, 'creator')->for($admin, 'updater')->create(['name' => 'Supplier A']);
    $warehouse = Warehouse::factory()->for($admin, 'creator')->for($admin, 'updater')->create(['name' => 'Main Warehouse']);
    $order = PurchaseOrder::query()->create(['supplier_id' => $supplier->id, 'purchase_order_number' => 'PO-1', 'order_date' => '2026-07-18', 'supplier_name' => $supplier->name, 'delivery_location' => 'Main office', 'status' => PurchaseOrderStatus::Issued, 'created_by' => $admin->id, 'updated_by' => $admin->id]);
    $line = $order->lines()->create(['product_service_id' => $product->id, 'line_number' => 1, 'item_type' => 'product', 'sku' => 'PC-1', 'description' => 'Computer snapshot', 'uom_code' => 'PC', 'uom_name' => 'Piece', 'ordered_quantity' => '5.0000', 'received_quantity' => '0.0000', 'billed_quantity' => '0.0000', 'cancelled_quantity' => '0.0000', 'unit_cost' => '1000.0000', 'discount_rate' => '0.000000', 'gross_amount' => '5000.0000', 'discount_amount' => '0.0000', 'net_amount' => '5000.0000']);
    $business = BusinessProfile::factory()->active()->create();
    $year = FiscalYear::factory()->for($business)->for($admin, 'creator')->create(['starts_on' => '2026-01-01', 'ends_on' => '2026-12-31']);
    DocumentSequence::query()->create(['business_profile_id' => $business->id, 'fiscal_year_id' => $year->id, 'fiscal_year_scope' => $year->id, 'document_type' => 'receiving_report', 'prefix' => 'RR-{YYYY}-', 'suffix' => '', 'current_number' => 0, 'padding' => 6, 'reset_rule' => 'fiscal_year', 'active' => true, 'created_by' => $admin->id, 'updated_by' => $admin->id]);

    return compact('admin', 'supplier', 'warehouse', 'order', 'line');
}

function receivingData(array $f, string $received, string $accepted, string $rejected = '0.0000', ?string $reason = null): array
{
    return ['purchase_order_id' => $f['order']->id, 'warehouse_id' => $f['warehouse']->id, 'receiving_date' => '2026-07-18', 'delivery_location' => 'Main Warehouse', 'delivery_receipt_number' => 'DR-1', 'inspection_reference' => 'IR-1', 'received_by' => $f['admin']->id, 'inspected_by' => $f['admin']->id, 'accepted_by' => $f['admin']->id, 'lines' => [['purchase_order_line_id' => $f['line']->id, 'received_quantity' => $received, 'accepted_quantity' => $accepted, 'rejected_quantity' => $rejected, 'rejection_reason' => $reason]]];
}

function createReceiving($test, array $f, string $received, string $accepted, string $rejected = '0.0000', ?string $reason = null): ReceivingRecord
{
    $test->actingAs($f['admin'])->post(route('receiving-records.store'), receivingData($f, $received, $accepted, $rejected, $reason))->assertRedirect();

    return ReceivingRecord::query()->latest('id')->firstOrFail();
}

test('partial and full receipts reconcile purchase-order quantities', function () {
    $f = receivingFixtures();
    $first = createReceiving($this, $f, '2.0000', '2.0000');
    $this->patch(route('receiving-records.transition', $first), ['status' => 'received'])->assertSessionHasNoErrors();
    $this->patch(route('receiving-records.transition', $first), ['status' => 'accepted'])->assertSessionHasNoErrors();
    expect($f['line']->fresh()->received_quantity)->toBe('2.0000')->and($f['order']->fresh()->status)->toBe(PurchaseOrderStatus::PartiallyReceived)->and($first->fresh()->receiving_number)->toBe('RR-2026-000001');
    $second = createReceiving($this, $f, '3.0000', '3.0000');
    $this->patch(route('receiving-records.transition', $second), ['status' => 'received'])->assertSessionHasNoErrors();
    $this->patch(route('receiving-records.transition', $second), ['status' => 'accepted'])->assertSessionHasNoErrors();
    expect($f['line']->fresh()->received_quantity)->toBe('5.0000')->and($f['order']->fresh()->status)->toBe(PurchaseOrderStatus::FullyReceived);
});

test('accepted and rejected quantities remain separate and reconcile credited quantity', function () {
    $f = receivingFixtures();
    $record = createReceiving($this, $f, '2.0000', '1.0000', '1.0000', 'Damaged casing');
    $this->patch(route('receiving-records.transition', $record), ['status' => 'received'])->assertSessionHasNoErrors();
    expect($f['line']->fresh()->received_quantity)->toBe('2.0000');
    $this->patch(route('receiving-records.transition', $record), ['status' => 'inspected'])->assertSessionHasNoErrors();
    $this->patch(route('receiving-records.transition', $record), ['status' => 'partially_accepted'])->assertSessionHasNoErrors();
    expect($f['line']->fresh()->received_quantity)->toBe('1.0000')->and($record->fresh()->status)->toBe(ReceivingStatus::PartiallyAccepted)->and($record->lines()->sole()->rejection_reason)->toBe('Damaged casing')->and($record->lines()->sole()->credited_quantity)->toBe('1.0000');
});

test('over receipt is blocked without changing purchase-order quantities', function () {
    $f = receivingFixtures();
    $record = createReceiving($this, $f, '6.0000', '6.0000');
    $this->patch(route('receiving-records.transition', $record), ['status' => 'received'])->assertSessionHasErrors('lines');
    expect($f['line']->fresh()->received_quantity)->toBe('0.0000')->and($record->fresh()->status)->toBe(ReceivingStatus::Draft);
});

test('cancellation requires a reason and reverses the exact credited quantity', function () {
    $f = receivingFixtures();
    $record = createReceiving($this, $f, '2.0000', '1.0000', '1.0000', 'Damaged');
    $this->patch(route('receiving-records.transition', $record), ['status' => 'received']);
    $this->patch(route('receiving-records.transition', $record), ['status' => 'partially_accepted']);
    expect($f['line']->fresh()->received_quantity)->toBe('1.0000');
    $this->patch(route('receiving-records.transition', $record), ['status' => 'cancelled'])->assertSessionHasErrors('reason');
    $this->patch(route('receiving-records.transition', $record), ['status' => 'cancelled', 'reason' => 'Receipt entered twice'])->assertSessionHasNoErrors();
    expect($f['line']->fresh()->received_quantity)->toBe('0.0000')->and($f['order']->fresh()->status)->toBe(PurchaseOrderStatus::Issued)->and($record->fresh()->cancellation_reason)->toBe('Receipt entered twice');
});

test('receiving access and status actions are authorized and printable', function () {
    $f = receivingFixtures();
    $record = createReceiving($this, $f, '1.0000', '1.0000');
    $viewer = User::factory()->create();
    $viewer->assignRole('Viewer');
    $this->actingAs($viewer)->get(route('receiving-records.index'))->assertSuccessful();
    $this->get(route('receiving-records.print', $record))->assertSuccessful()->assertSee('Computer snapshot');
    $this->patch(route('receiving-records.transition', $record), ['status' => 'received'])->assertForbidden();
    $this->post(route('receiving-records.store'), receivingData($f, '1.0000', '1.0000'))->assertForbidden();
});

test('receiving creates no inventory valuation payable or journal effects', function () {
    $f = receivingFixtures();
    createReceiving($this, $f, '1.0000', '1.0000');
    expect(Schema::hasTable('inventory_movements'))->toBeFalse()->and(Schema::hasTable('inventory_balances'))->toBeFalse()->and(SupplierInvoice::query()->count())->toBe(0)->and(Schema::hasTable('journal_entries'))->toBeFalse();
});
