<?php

use App\Enums\DeliveryStatus;
use App\Enums\SalesInvoiceStatus;
use App\Models\BusinessProfile;
use App\Models\Customer;
use App\Models\Delivery;
use App\Models\DeliveryLine;
use App\Models\DocumentSequence;
use App\Models\FiscalPeriod;
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

function invoiceFixture(): array
{
    $user = User::factory()->administrator()->create();
    $customer = Customer::factory()->create(['name' => 'Agency Customer', 'tin' => '123-456-789', 'address' => 'Snapshot Address']);
    $business = BusinessProfile::factory()->active()->create();
    $year = FiscalYear::factory()->for($business)->for($user, 'creator')->create(['starts_on' => '2026-05-01', 'ends_on' => '2026-12-31']);
    $period = FiscalPeriod::factory()->for($year)->create(['name' => 'July 2026', 'starts_on' => '2026-07-01', 'ends_on' => '2026-07-31', 'calendar_year' => 2026, 'calendar_month' => 7, 'status' => 'open']);
    DocumentSequence::create(['business_profile_id' => $business->id, 'fiscal_year_id' => $year->id, 'fiscal_year_scope' => $year->id, 'document_type' => 'sales_invoice', 'prefix' => 'SI-{YYYY}-', 'suffix' => '', 'current_number' => 0, 'padding' => 6, 'reset_rule' => 'fiscal_year', 'active' => true, 'created_by' => $user->id, 'updated_by' => $user->id]);

    return compact('user', 'customer', 'period');
}

function directInvoiceData(array $fixture): array
{
    return ['source_type' => 'direct', 'customer_id' => $fixture['customer']->id, 'fiscal_period_id' => $fixture['period']->id,
        'invoice_date' => '2026-07-16', 'due_date' => '2026-08-15', 'expected_withholding_amount' => '20.0000',
        'lines' => [['description' => 'Installation service', 'uom_code' => 'JOB', 'uom_name' => 'Job', 'quantity' => '2.0000', 'unit_price' => '500.0000', 'discount_rate' => '10.000000']]];
}

test('direct service invoice drafts and posts with separated receivable amounts and one number', function () {
    $fixture = invoiceFixture();
    $this->actingAs($fixture['user'])->post(route('sales-invoices.store'), directInvoiceData($fixture))->assertRedirect();
    $invoice = SalesInvoice::sole();
    expect($invoice->status)->toBe(SalesInvoiceStatus::Draft)->and($invoice->invoice_number)->toBeNull()
        ->and($invoice->gross_amount)->toBe('1000.0000')->and($invoice->discount_amount)->toBe('100.0000')
        ->and($invoice->net_sales_amount)->toBe('900.0000')->and($invoice->expected_withholding_amount)->toBe('20.0000')
        ->and($invoice->total_receivable)->toBe('880.0000')->and($invoice->balance_due)->toBe('880.0000')
        ->and($invoice->customer_tin)->toBe('123-456-789')->and($invoice->billing_address)->toBe('Snapshot Address');
    $this->actingAs($fixture['user'])->patch(route('sales-invoices.transition', $invoice), ['status' => 'posted'])->assertSessionHasNoErrors();
    expect($invoice->fresh()->invoice_number)->toBe('SI-2026-000001')->and($invoice->fresh()->status)->toBe(SalesInvoiceStatus::Posted);
    $this->actingAs($fixture['user'])->patch(route('sales-invoices.transition', $invoice), ['status' => 'posted'])->assertSessionHasErrors('status');
    expect(DocumentSequence::where('document_type', 'sales_invoice')->value('current_number'))->toBe(1);
});

test('order and delivery source quantities cannot be over invoiced', function () {
    $fixture = invoiceFixture();
    $order = SalesOrder::factory()->for($fixture['customer'])->create(['customer_id' => $fixture['customer']->id]);
    $orderLine = SalesOrderLine::factory()->for($order)->create(['ordered_quantity' => '10.0000', 'invoiced_quantity' => '0.0000']);
    $data = array_replace(directInvoiceData($fixture), ['source_type' => 'order', 'sales_order_id' => $order->id]);
    $data['lines'] = [['sales_order_line_id' => $orderLine->id, 'quantity' => '11.0000']];
    $this->actingAs($fixture['user'])->post(route('sales-invoices.store'), $data);
    $invoice = SalesInvoice::sole();
    $this->actingAs($fixture['user'])->patch(route('sales-invoices.transition', $invoice), ['status' => 'posted'])->assertSessionHasErrors('lines');
    expect($orderLine->fresh()->invoiced_quantity)->toBe('0.0000');

    $delivery = Delivery::factory()->for($order)->for($fixture['customer'])->create(['status' => DeliveryStatus::Released]);
    $deliveryLine = DeliveryLine::factory()->for($delivery)->for($orderLine)->create(['delivered_quantity' => '4.0000']);
    $deliveryData = array_replace(directInvoiceData($fixture), ['source_type' => 'delivery', 'delivery_id' => $delivery->id]);
    $deliveryData['lines'] = [['delivery_line_id' => $deliveryLine->id, 'quantity' => '5.0000']];
    $this->actingAs($fixture['user'])->post(route('sales-invoices.store'), $deliveryData);
    $deliveryInvoice = SalesInvoice::latest('id')->first();
    $this->actingAs($fixture['user'])->patch(route('sales-invoices.transition', $deliveryInvoice), ['status' => 'posted'])->assertSessionHasErrors('lines');
});

test('posting reconciles source quantities and voiding reverses them with a reason', function () {
    $fixture = invoiceFixture();
    $order = SalesOrder::factory()->for($fixture['customer'])->create(['customer_id' => $fixture['customer']->id]);
    $line = SalesOrderLine::factory()->for($order)->create(['ordered_quantity' => '10.0000', 'invoiced_quantity' => '0.0000']);
    $data = array_replace(directInvoiceData($fixture), ['source_type' => 'order', 'sales_order_id' => $order->id]);
    $data['lines'] = [['sales_order_line_id' => $line->id, 'quantity' => '4.0000']];
    $this->actingAs($fixture['user'])->post(route('sales-invoices.store'), $data);
    $invoice = SalesInvoice::sole();
    $this->actingAs($fixture['user'])->patch(route('sales-invoices.transition', $invoice), ['status' => 'posted']);
    expect($line->fresh()->invoiced_quantity)->toBe('4.0000');
    $this->actingAs($fixture['user'])->put(route('sales-invoices.update', $invoice), $data)->assertForbidden();
    $this->actingAs($fixture['user'])->patch(route('sales-invoices.transition', $invoice), ['status' => 'voided'])->assertSessionHasErrors('reason');
    $this->actingAs($fixture['user'])->patch(route('sales-invoices.transition', $invoice), ['status' => 'voided', 'reason' => 'Customer cancelled'])->assertSessionHasNoErrors();
    expect($line->fresh()->invoiced_quantity)->toBe('0.0000')->and($invoice->fresh()->void_reason)->toBe('Customer cancelled');
});

test('invoice authorization printing and prohibited downstream effects are enforced', function () {
    $fixture = invoiceFixture();
    $this->actingAs($fixture['user'])->post(route('sales-invoices.store'), directInvoiceData($fixture));
    $invoice = SalesInvoice::sole();
    $viewer = User::factory()->create();
    $viewer->assignRole('Viewer');
    $this->actingAs($viewer)->get(route('sales-invoices.index'))->assertSuccessful();
    $this->actingAs($viewer)->get(route('sales-invoices.print', $invoice))->assertSuccessful();
    $this->actingAs($viewer)->post(route('sales-invoices.store'), directInvoiceData($fixture))->assertForbidden();
    $this->actingAs($viewer)->patch(route('sales-invoices.transition', $invoice), ['status' => 'posted'])->assertForbidden();
    expect(Schema::hasTable('journal_entries'))->toBeFalse()->and(Schema::hasTable('tax_returns'))->toBeFalse();
});
