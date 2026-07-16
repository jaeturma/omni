<?php

use App\Enums\QuotationStatus;
use App\Models\BusinessProfile;
use App\Models\Category;
use App\Models\Customer;
use App\Models\CustomerPayment;
use App\Models\DocumentSequence;
use App\Models\FiscalYear;
use App\Models\ProductService;
use App\Models\Quotation;
use App\Models\SalesInvoice;
use App\Models\UnitOfMeasure;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(LazilyRefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

function quotationFixtures(): array
{
    $admin = User::factory()->administrator()->create();
    $customer = Customer::factory()->for($admin, 'creator')->for($admin, 'updater')->create(['name' => 'Original Customer', 'address' => 'Original Address']);
    $unit = UnitOfMeasure::factory()->for($admin, 'creator')->for($admin, 'updater')->create(['code' => 'PC', 'name' => 'Piece']);
    $category = Category::factory()->for($admin, 'creator')->for($admin, 'updater')->create(['type' => 'product']);
    $item = ProductService::factory()->for($category)->for($unit, 'unitOfMeasure')->for($admin, 'creator')->for($admin, 'updater')->create(['sku' => 'ITEM-1', 'name' => 'Original Item', 'type' => 'product']);

    return compact('admin', 'customer', 'unit', 'category', 'item');
}

function quotationData(Customer $customer, ProductService $item, array $changes = []): array
{
    return array_replace([
        'customer_id' => $customer->id, 'quotation_date' => '2026-07-16', 'valid_until' => '2026-08-15',
        'contact_name' => 'Buyer', 'contact_email' => 'buyer@example.com', 'contact_phone' => '09170000000',
        'billing_address' => 'Billing snapshot', 'delivery_address' => 'Delivery snapshot', 'reference' => 'RFQ-1',
        'notes' => 'Notes', 'terms_and_conditions' => 'Thirty days', 'document_discount_rate' => '5.000000',
        'lines' => [['product_service_id' => $item->id, 'description' => 'Item snapshot', 'quantity' => '2.5000', 'unit_price' => '100.0000', 'discount_rate' => '10.000000']],
    ], $changes);
}

function createQuotationViaRequest($test): array
{
    $fixtures = quotationFixtures();
    $test->actingAs($fixtures['admin'])->post(route('quotations.store'), quotationData($fixtures['customer'], $fixtures['item']))->assertRedirect();

    return $fixtures + ['quotation' => Quotation::query()->with('lines')->sole()];
}

function configureQuotationSequence(array $fixtures): void
{
    $business = BusinessProfile::factory()->active()->create();
    $year = FiscalYear::factory()->for($business)->for($fixtures['admin'], 'creator')->create(['starts_on' => '2026-05-01', 'ends_on' => '2026-12-31']);
    DocumentSequence::query()->create(['business_profile_id' => $business->id, 'fiscal_year_id' => $year->id, 'fiscal_year_scope' => $year->id, 'document_type' => 'quotation', 'prefix' => 'QT-{YYYY}-', 'suffix' => '', 'current_number' => 0, 'padding' => 6, 'reset_rule' => 'fiscal_year', 'active' => true, 'created_by' => $fixtures['admin']->id, 'updated_by' => $fixtures['admin']->id]);
}

test('authorized users create and update draft quotations with decimal totals and snapshots', function () {
    $fixtures = createQuotationViaRequest($this);
    $quotation = $fixtures['quotation'];
    expect($quotation->status)->toBe(QuotationStatus::Draft)->and($quotation->subtotal)->toBe('250.0000')->and($quotation->line_discount_total)->toBe('25.0000')->and($quotation->document_discount_amount)->toBe('11.2500')->and($quotation->grand_total)->toBe('213.7500')->and($quotation->customer_name)->toBe('Original Customer')->and($quotation->lines->first()->sku)->toBe('ITEM-1')->and($quotation->lines->first()->uom_code)->toBe('PC');

    $fixtures['customer']->update(['name' => 'Changed Customer']);
    $fixtures['item']->update(['sku' => 'CHANGED']);
    expect($quotation->fresh()->customer_name)->toBe('Original Customer')->and($quotation->lines()->first()->sku)->toBe('ITEM-1');

    $this->actingAs($fixtures['admin'])->put(route('quotations.update', $quotation), quotationData($fixtures['customer'], $fixtures['item'], ['reference' => 'UPDATED']))->assertRedirect();
    expect($quotation->fresh()->reference)->toBe('UPDATED');
});

test('quotation validation requires valid dates addresses and product or service lines', function () {
    $fixtures = quotationFixtures();
    $this->actingAs($fixtures['admin'])->post(route('quotations.store'), quotationData($fixtures['customer'], $fixtures['item'], ['valid_until' => '2026-01-01', 'billing_address' => '', 'lines' => []]))->assertSessionHasErrors(['valid_until', 'billing_address', 'lines']);
});

test('submission consumes a sequence number and approved quotations are immutable', function () {
    $fixtures = createQuotationViaRequest($this);
    configureQuotationSequence($fixtures);
    $quotation = $fixtures['quotation'];
    $this->actingAs($fixtures['admin'])->patch(route('quotations.transition', $quotation), ['status' => 'submitted'])->assertSessionHasNoErrors();
    expect($quotation->fresh()->quotation_number)->toBe('QT-2026-000001')->and($quotation->fresh()->status)->toBe(QuotationStatus::Submitted);
    $this->actingAs($fixtures['admin'])->patch(route('quotations.transition', $quotation), ['status' => 'approved'])->assertSessionHasNoErrors();
    $this->actingAs($fixtures['admin'])->get(route('quotations.edit', $quotation))->assertForbidden();
    $this->actingAs($fixtures['admin'])->put(route('quotations.update', $quotation), quotationData($fixtures['customer'], $fixtures['item']))->assertForbidden();
});

test('quotation cancellation requires a reason', function () {
    $fixtures = createQuotationViaRequest($this);
    $quotation = $fixtures['quotation'];
    $this->actingAs($fixtures['admin'])->patch(route('quotations.transition', $quotation), ['status' => 'cancelled'])->assertSessionHasErrors('reason');
    $this->actingAs($fixtures['admin'])->patch(route('quotations.transition', $quotation), ['status' => 'cancelled', 'reason' => 'Customer withdrew request'])->assertSessionHasNoErrors();
    expect($quotation->fresh()->cancellation_reason)->toBe('Customer withdrew request')->and($quotation->fresh()->cancelled_by)->toBe($fixtures['admin']->id);
});

test('quotation access is authorized and print is available to viewers', function () {
    $fixtures = createQuotationViaRequest($this);
    $quotation = $fixtures['quotation'];
    $viewer = User::factory()->create();
    $viewer->assignRole('Viewer');
    $this->actingAs($viewer)->get(route('quotations.index'))->assertSuccessful();
    $this->actingAs($viewer)->get(route('quotations.print', $quotation))->assertSuccessful()->assertSee('Item snapshot');
    $this->actingAs($viewer)->post(route('quotations.store'), quotationData($fixtures['customer'], $fixtures['item']))->assertForbidden();
    auth()->logout();
    $this->get(route('quotations.index'))->assertRedirect(route('login'));
});

test('quotations create no downstream financial inventory or order effects', function () {
    createQuotationViaRequest($this);
    expect(Schema::hasTable('sales_orders'))->toBeTrue()->and(Schema::hasTable('sales_invoices'))->toBeTrue()->and(SalesInvoice::count())->toBe(0)->and(Schema::hasTable('customer_payments'))->toBeTrue()->and(CustomerPayment::count())->toBe(0)->and(Schema::hasTable('inventory_movements'))->toBeFalse()->and(Schema::hasTable('journal_entries'))->toBeFalse();
});
