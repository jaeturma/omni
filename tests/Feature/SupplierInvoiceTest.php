<?php

use App\Enums\PurchaseOrderStatus;
use App\Enums\ReceivingStatus;
use App\Enums\SupplierInvoiceStatus;
use App\Models\BusinessProfile;
use App\Models\DocumentSequence;
use App\Models\FiscalPeriod;
use App\Models\FiscalYear;
use App\Models\PurchaseOrder;
use App\Models\ReceivingRecord;
use App\Models\Supplier;
use App\Models\SupplierInvoice;
use App\Models\SupplierPayment;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(LazilyRefreshDatabase::class);
beforeEach(fn () => $this->seed(RolesAndPermissionsSeeder::class));

function supplierInvoiceFixtures(): array
{
    $admin = User::factory()->administrator()->create();
    $supplier = Supplier::factory()->for($admin, 'creator')->for($admin, 'updater')->create(['name' => 'ABC Supplies', 'tin' => '123-456-789', 'address' => 'Manila']);
    $business = BusinessProfile::factory()->active()->create();
    $year = FiscalYear::factory()->for($business)->for($admin, 'creator')->create(['starts_on' => '2026-01-01', 'ends_on' => '2026-12-31']);
    $period = FiscalPeriod::factory()->for($year)->create(['name' => 'July 2026', 'starts_on' => '2026-07-01', 'ends_on' => '2026-07-31', 'calendar_year' => 2026, 'calendar_month' => 7, 'calendar_quarter' => 3, 'status' => 'open']);
    DocumentSequence::query()->create(['business_profile_id' => $business->id, 'fiscal_year_id' => $year->id, 'fiscal_year_scope' => $year->id, 'document_type' => 'purchase_invoice', 'prefix' => 'PI-{YYYY}-', 'suffix' => '', 'current_number' => 0, 'padding' => 6, 'reset_rule' => 'fiscal_year', 'active' => true, 'created_by' => $admin->id, 'updated_by' => $admin->id]);
    $order = PurchaseOrder::query()->create(['supplier_id' => $supplier->id, 'purchase_order_number' => 'PO-INV-1', 'order_date' => '2026-07-01', 'supplier_name' => $supplier->name, 'delivery_location' => 'Office', 'status' => PurchaseOrderStatus::PartiallyReceived, 'created_by' => $admin->id, 'updated_by' => $admin->id]);
    $orderLine = $order->lines()->create(['line_number' => 1, 'item_type' => 'product', 'description' => 'Laptop', 'uom_code' => 'PC', 'uom_name' => 'Piece', 'ordered_quantity' => '5.0000', 'received_quantity' => '2.0000', 'billed_quantity' => '0.0000', 'cancelled_quantity' => '0.0000', 'unit_cost' => '1000.0000', 'discount_rate' => '10.000000', 'gross_amount' => '5000.0000', 'discount_amount' => '500.0000', 'net_amount' => '4500.0000']);
    $receipt = ReceivingRecord::query()->create(['purchase_order_id' => $order->id, 'supplier_id' => $supplier->id, 'receiving_number' => 'RR-1', 'receiving_date' => '2026-07-10', 'supplier_name' => $supplier->name, 'delivery_location' => 'Office', 'received_by' => $admin->id, 'status' => ReceivingStatus::Accepted, 'created_by' => $admin->id, 'updated_by' => $admin->id]);
    $receiptLine = $receipt->lines()->create(['purchase_order_line_id' => $orderLine->id, 'line_number' => 1, 'item_type' => 'product', 'description' => 'Laptop', 'uom_code' => 'PC', 'uom_name' => 'Piece', 'received_quantity' => '2.0000', 'accepted_quantity' => '2.0000', 'rejected_quantity' => '0.0000', 'credited_quantity' => '2.0000']);

    return compact('admin', 'supplier', 'period', 'order', 'orderLine', 'receipt', 'receiptLine');
}

function supplierInvoiceData(array $f, string $number = 'SI-100', string $quantity = '2.0000'): array
{
    return ['supplier_id' => $f['supplier']->id, 'purchase_order_id' => $f['order']->id, 'receiving_record_id' => $f['receipt']->id, 'fiscal_period_id' => $f['period']->id, 'supplier_invoice_number' => $number, 'invoice_date' => '2026-07-15', 'due_date' => '2026-08-15', 'freight_amount' => '100.0000', 'other_charges_amount' => '50.0000', 'withholding_expected_amount' => '100.0000', 'lines' => [['purchase_order_line_id' => $f['orderLine']->id, 'receiving_record_line_id' => $f['receiptLine']->id, 'item_type' => 'product', 'description' => 'Laptop snapshot', 'uom_code' => 'PC', 'uom_name' => 'Piece', 'quantity' => $quantity, 'unit_cost' => '1000.0000', 'discount_rate' => '10.000000']]];
}

function saveSupplierInvoice($test, array $f, array $overrides = []): SupplierInvoice
{
    $test->actingAs($f['admin'])->post(route('supplier-invoices.store'), array_replace_recursive(supplierInvoiceData($f), $overrides))->assertRedirect();

    return SupplierInvoice::query()->latest('id')->firstOrFail();
}

test('draft posting issues one internal number and establishes separated payable amounts', function () {
    $f = supplierInvoiceFixtures();
    $invoice = saveSupplierInvoice($this, $f);
    expect($invoice->status)->toBe(SupplierInvoiceStatus::Draft)->and($invoice->internal_number)->toBeNull()->and($invoice->gross_purchase_amount)->toBe('2000.0000')->and($invoice->discount_amount)->toBe('200.0000')->and($invoice->net_purchase_amount)->toBe('1800.0000')->and($invoice->withholding_expected_amount)->toBe('100.0000')->and($invoice->total_payable)->toBe('1850.0000')->and($invoice->balance_due)->toBe('1850.0000');
    $this->patch(route('supplier-invoices.transition', $invoice), ['status' => 'posted'])->assertSessionHasNoErrors();
    expect($invoice->fresh()->internal_number)->toBe('PI-2026-000001')->and($invoice->fresh()->status)->toBe(SupplierInvoiceStatus::Posted)->and($f['orderLine']->fresh()->billed_quantity)->toBe('2.0000');
    $this->patch(route('supplier-invoices.transition', $invoice), ['status' => 'posted'])->assertForbidden();
    expect(DocumentSequence::query()->where('document_type', 'purchase_invoice')->value('current_number'))->toBe(1);
});

test('duplicate supplier invoice numbers are prevented per supplier', function () {
    $f = supplierInvoiceFixtures();
    saveSupplierInvoice($this, $f);
    $this->post(route('supplier-invoices.store'), supplierInvoiceData($f))->assertSessionHasErrors('supplier_invoice_number');
    expect(SupplierInvoice::query()->count())->toBe(1);
});

test('direct service invoice is allowed without purchase or receiving source', function () {
    $f = supplierInvoiceFixtures();
    $data = supplierInvoiceData($f, 'DIRECT-1', '1.0000');
    unset($data['purchase_order_id'], $data['receiving_record_id'], $data['lines'][0]['purchase_order_line_id'], $data['lines'][0]['receiving_record_line_id']);
    $data['lines'][0] = array_replace($data['lines'][0], ['item_type' => 'service', 'description' => 'Internet service', 'uom_code' => 'MO', 'uom_name' => 'Month', 'unit_cost' => '2500.0000', 'discount_rate' => '0']);
    $this->actingAs($f['admin'])->post(route('supplier-invoices.store'), $data)->assertRedirect();
    expect(SupplierInvoice::query()->sole()->purchase_order_id)->toBeNull()->and(SupplierInvoice::query()->sole()->gross_purchase_amount)->toBe('2500.0000');
});

test('posting prevents billing above accepted source quantities', function () {
    $f = supplierInvoiceFixtures();
    $invoice = saveSupplierInvoice($this, $f, ['lines' => [['quantity' => '2.0001']]]);
    $this->patch(route('supplier-invoices.transition', $invoice), ['status' => 'posted'])->assertSessionHasErrors('lines');
    expect($invoice->fresh()->status)->toBe(SupplierInvoiceStatus::Draft)->and($f['orderLine']->fresh()->billed_quantity)->toBe('0.0000');
});

test('posted invoices are immutable and voiding requires reason and reverses source quantity', function () {
    $f = supplierInvoiceFixtures();
    $invoice = saveSupplierInvoice($this, $f);
    $this->patch(route('supplier-invoices.transition', $invoice), ['status' => 'posted']);
    $this->put(route('supplier-invoices.update', $invoice), supplierInvoiceData($f, 'CHANGED'))->assertForbidden();
    $this->delete(route('supplier-invoices.destroy', $invoice))->assertForbidden();
    $this->patch(route('supplier-invoices.transition', $invoice), ['status' => 'voided'])->assertSessionHasErrors('reason');
    $this->patch(route('supplier-invoices.transition', $invoice), ['status' => 'voided', 'reason' => 'Duplicate supplier billing'])->assertSessionHasNoErrors();
    expect($invoice->fresh()->status)->toBe(SupplierInvoiceStatus::Voided)->and($invoice->fresh()->balance_due)->toBe('0.0000')->and($invoice->fresh()->void_reason)->toBe('Duplicate supplier billing')->and($f['orderLine']->fresh()->billed_quantity)->toBe('0.0000');
});

test('supplier invoice access is authorized and printable', function () {
    $f = supplierInvoiceFixtures();
    $invoice = saveSupplierInvoice($this, $f);
    $viewer = User::factory()->create();
    $viewer->assignRole('Viewer');
    $this->actingAs($viewer)->get(route('supplier-invoices.index'))->assertSuccessful();
    $this->get(route('supplier-invoices.print', $invoice))->assertSuccessful()->assertSee('Laptop snapshot');
    $this->post(route('supplier-invoices.store'), supplierInvoiceData($f, 'NOPE'))->assertForbidden();
    $this->patch(route('supplier-invoices.transition', $invoice), ['status' => 'posted'])->assertForbidden();
});

test('supplier invoices create no journal entries or tax returns', function () {
    $f = supplierInvoiceFixtures();
    saveSupplierInvoice($this, $f);
    expect(Schema::hasTable('journal_entries'))->toBeFalse()->and(Schema::hasTable('tax_returns'))->toBeFalse()->and(SupplierPayment::query()->count())->toBe(0);
});
