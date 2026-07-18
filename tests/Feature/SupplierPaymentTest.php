<?php

use App\Enums\SupplierInvoiceStatus;
use App\Enums\SupplierPaymentAllocationStatus;
use App\Enums\SupplierPaymentStatus;
use App\Models\BusinessProfile;
use App\Models\DocumentSequence;
use App\Models\FiscalPeriod;
use App\Models\FiscalYear;
use App\Models\PaymentMethod;
use App\Models\Supplier;
use App\Models\SupplierInvoice;
use App\Models\SupplierPayment;
use App\Models\SupplierPaymentAllocation;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(LazilyRefreshDatabase::class);
beforeEach(fn () => $this->seed(RolesAndPermissionsSeeder::class));

function supplierPaymentFixtures(): array
{
    $admin = User::factory()->administrator()->create();
    $supplier = Supplier::factory()->for($admin, 'creator')->for($admin, 'updater')->create(['name' => 'Payment Supplier']);
    $method = PaymentMethod::factory()->for($admin, 'creator')->for($admin, 'updater')->create();
    $business = BusinessProfile::factory()->active()->create();
    $year = FiscalYear::factory()->for($business)->for($admin, 'creator')->create(['starts_on' => '2026-01-01', 'ends_on' => '2026-12-31']);
    $period = FiscalPeriod::factory()->for($year)->create(['name' => 'July 2026', 'starts_on' => '2026-07-01', 'ends_on' => '2026-07-31', 'calendar_year' => 2026, 'calendar_month' => 7, 'calendar_quarter' => 3, 'status' => 'open']);
    DocumentSequence::query()->create(['business_profile_id' => $business->id, 'fiscal_year_id' => $year->id, 'fiscal_year_scope' => $year->id, 'document_type' => 'supplier_payment', 'prefix' => 'SP-{YYYY}-', 'suffix' => '', 'current_number' => 0, 'padding' => 6, 'reset_rule' => 'fiscal_year', 'active' => true, 'created_by' => $admin->id, 'updated_by' => $admin->id]);

    return compact('admin', 'supplier', 'method', 'period');
}

function supplierPaymentData(array $f, string $gross = '1000.0000'): array
{
    return ['supplier_id' => $f['supplier']->id, 'payment_method_id' => $f['method']->id, 'payment_date' => '2026-07-18', 'reference_number' => 'TR-100', 'gross_settlement_amount' => $gross, 'withholding_amount' => '50.0000', 'other_deductions' => '20.0000', 'net_cash_paid' => bcsub($gross, '70.0000', 4)];
}

function openSupplierInvoice(array $f, string $amount = '1000.0000'): SupplierInvoice
{
    return SupplierInvoice::query()->create(['supplier_id' => $f['supplier']->id, 'fiscal_period_id' => $f['period']->id, 'internal_number' => fake()->unique()->numerify('PI-######'), 'supplier_invoice_number' => fake()->unique()->numerify('REF-######'), 'invoice_date' => '2026-07-10', 'due_date' => '2026-08-10', 'supplier_name' => $f['supplier']->name, 'gross_purchase_amount' => $amount, 'net_purchase_amount' => $amount, 'total_payable' => $amount, 'paid_amount' => '0.0000', 'balance_due' => $amount, 'status' => SupplierInvoiceStatus::Posted, 'posted_at' => now(), 'posted_by' => $f['admin']->id, 'created_by' => $f['admin']->id, 'updated_by' => $f['admin']->id]);
}

function postedSupplierPayment(array $f, string $amount): SupplierPayment
{
    return SupplierPayment::query()->create(['supplier_id' => $f['supplier']->id, 'payment_method_id' => $f['method']->id, 'payment_date' => '2026-07-18', 'gross_settlement_amount' => $amount, 'net_cash_paid' => $amount, 'unapplied_amount' => $amount, 'status' => SupplierPaymentStatus::Posted, 'created_by' => $f['admin']->id, 'updated_by' => $f['admin']->id]);
}

test('payment draft posts once and preserves settlement components and advance balance', function () {
    $f = supplierPaymentFixtures();
    $this->actingAs($f['admin'])->post(route('supplier-payments.store'), supplierPaymentData($f))->assertRedirect();
    $payment = SupplierPayment::query()->sole();
    expect($payment->status)->toBe(SupplierPaymentStatus::Draft)->and($payment->payment_number)->toBeNull()->and($payment->gross_settlement_amount)->toBe('1000.0000')->and($payment->withholding_amount)->toBe('50.0000')->and($payment->other_deductions)->toBe('20.0000')->and($payment->net_cash_paid)->toBe('930.0000')->and($payment->unapplied_amount)->toBe('1000.0000');
    $this->patch(route('supplier-payments.transition', $payment), ['status' => 'posted'])->assertSessionHasNoErrors();
    expect($payment->fresh()->payment_number)->toBe('SP-2026-000001')->and($payment->fresh()->status)->toBe(SupplierPaymentStatus::Posted);
    $this->patch(route('supplier-payments.transition', $payment), ['status' => 'posted'])->assertForbidden();
});

test('one payment fully and partially settles multiple invoices', function () {
    $f = supplierPaymentFixtures();
    $first = openSupplierInvoice($f, '600.0000');
    $second = openSupplierInvoice($f, '800.0000');
    $payment = postedSupplierPayment($f, '1000.0000');
    $this->actingAs($f['admin'])->post(route('supplier-payments.allocate', $payment), ['allocations' => [['supplier_invoice_id' => $first->id, 'amount' => '600.0000'], ['supplier_invoice_id' => $second->id, 'amount' => '400.0000']]])->assertSessionHasNoErrors();
    expect($first->fresh()->status)->toBe(SupplierInvoiceStatus::Paid)->and($first->fresh()->balance_due)->toBe('0.0000')->and($second->fresh()->status)->toBe(SupplierInvoiceStatus::PartiallyPaid)->and($second->fresh()->balance_due)->toBe('400.0000')->and($payment->fresh()->status)->toBe(SupplierPaymentStatus::FullyAllocated)->and($payment->fresh()->unapplied_amount)->toBe('0.0000');
});

test('multiple payments partially settle one invoice and preserve unapplied advances', function () {
    $f = supplierPaymentFixtures();
    $invoice = openSupplierInvoice($f);
    foreach (['300.0000', '200.0000'] as $amount) {
        $payment = postedSupplierPayment($f, $amount);
        $this->actingAs($f['admin'])->post(route('supplier-payments.allocate', $payment), ['allocations' => [['supplier_invoice_id' => $invoice->id, 'amount' => $amount]]]);
    }
    $advance = postedSupplierPayment($f, '250.0000');
    expect($invoice->fresh()->paid_amount)->toBe('500.0000')->and($invoice->fresh()->balance_due)->toBe('500.0000')->and($invoice->fresh()->status)->toBe(SupplierInvoiceStatus::PartiallyPaid)->and($advance->unapplied_amount)->toBe('250.0000');
    $this->actingAs($f['admin'])->patch(route('supplier-invoices.transition', $invoice), ['status' => 'voided', 'reason' => 'Cannot void paid invoice'])->assertForbidden();
});

test('over allocation and cross supplier allocation roll back atomically', function () {
    $f = supplierPaymentFixtures();
    $invoice = openSupplierInvoice($f, '500.0000');
    $otherSupplier = Supplier::factory()->for($f['admin'], 'creator')->for($f['admin'], 'updater')->create();
    $other = SupplierInvoice::query()->create(['supplier_id' => $otherSupplier->id, 'fiscal_period_id' => $f['period']->id, 'supplier_invoice_number' => 'OTHER', 'invoice_date' => '2026-07-01', 'due_date' => '2026-08-01', 'supplier_name' => $otherSupplier->name, 'gross_purchase_amount' => '100.0000', 'net_purchase_amount' => '100.0000', 'total_payable' => '100.0000', 'balance_due' => '100.0000', 'status' => SupplierInvoiceStatus::Posted, 'created_by' => $f['admin']->id, 'updated_by' => $f['admin']->id]);
    $payment = postedSupplierPayment($f, '600.0000');
    $this->actingAs($f['admin'])->post(route('supplier-payments.allocate', $payment), ['allocations' => [['supplier_invoice_id' => $invoice->id, 'amount' => '400.0000'], ['supplier_invoice_id' => $other->id, 'amount' => '100.0000']]])->assertSessionHasErrors('allocations.1.supplier_invoice_id');
    expect($invoice->fresh()->paid_amount)->toBe('0.0000')->and(SupplierPaymentAllocation::query()->count())->toBe(0);
    $this->post(route('supplier-payments.allocate', $payment), ['allocations' => [['supplier_invoice_id' => $invoice->id, 'amount' => '500.0001']]])->assertSessionHasErrors('allocations.0.amount');
});

test('voiding requires reason and reverses active allocations', function () {
    $f = supplierPaymentFixtures();
    $invoice = openSupplierInvoice($f, '500.0000');
    $payment = postedSupplierPayment($f, '500.0000');
    $this->actingAs($f['admin'])->post(route('supplier-payments.allocate', $payment), ['allocations' => [['supplier_invoice_id' => $invoice->id, 'amount' => '500.0000']]]);
    $this->patch(route('supplier-payments.transition', $payment), ['status' => 'voided'])->assertSessionHasErrors('reason');
    $this->patch(route('supplier-payments.transition', $payment), ['status' => 'voided', 'reason' => 'Cheque stopped'])->assertSessionHasNoErrors();
    expect($payment->fresh()->status)->toBe(SupplierPaymentStatus::Voided)->and($payment->fresh()->unapplied_amount)->toBe('500.0000')->and($invoice->fresh()->paid_amount)->toBe('0.0000')->and($invoice->fresh()->balance_due)->toBe('500.0000')->and(SupplierPaymentAllocation::query()->sole()->status)->toBe(SupplierPaymentAllocationStatus::Reversed);
});

test('validation and authorization are enforced', function () {
    $f = supplierPaymentFixtures();
    $invalid = supplierPaymentData($f);
    $invalid['net_cash_paid'] = '900.0000';
    $this->actingAs($f['admin'])->post(route('supplier-payments.store'), $invalid)->assertSessionHasErrors('gross_settlement_amount');
    $viewer = User::factory()->create();
    $viewer->assignRole('Viewer');
    $this->actingAs($viewer)->get(route('supplier-payments.index'))->assertSuccessful();
    $this->post(route('supplier-payments.store'), supplierPaymentData($f))->assertForbidden();
});

test('supplier payments create no journal or tax-return effects', function () {
    $f = supplierPaymentFixtures();
    postedSupplierPayment($f, '100.0000');
    expect(Schema::hasTable('journal_entries'))->toBeFalse()->and(Schema::hasTable('tax_returns'))->toBeFalse();
});
