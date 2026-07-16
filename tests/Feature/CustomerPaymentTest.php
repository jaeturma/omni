<?php

use App\Enums\CustomerPaymentStatus;
use App\Enums\PaymentAllocationStatus;
use App\Enums\SalesInvoiceStatus;
use App\Models\BusinessProfile;
use App\Models\Customer;
use App\Models\CustomerPayment;
use App\Models\DocumentSequence;
use App\Models\FiscalPeriod;
use App\Models\FiscalYear;
use App\Models\PaymentAllocation;
use App\Models\PaymentMethod;
use App\Models\SalesInvoice;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(LazilyRefreshDatabase::class);
beforeEach(fn () => $this->seed(RolesAndPermissionsSeeder::class));

function paymentFixture(): array
{
    $user = User::factory()->administrator()->create();
    $customer = Customer::factory()->create();
    $method = PaymentMethod::factory()->create();
    $business = BusinessProfile::factory()->active()->create();
    $year = FiscalYear::factory()->for($business)->for($user, 'creator')->create(['starts_on' => '2026-01-01', 'ends_on' => '2026-12-31']);
    $period = FiscalPeriod::factory()->for($year)->create(['starts_on' => '2026-07-01', 'ends_on' => '2026-07-31', 'status' => 'open']);
    DocumentSequence::create(['business_profile_id' => $business->id, 'fiscal_year_id' => $year->id, 'fiscal_year_scope' => $year->id,
        'document_type' => 'collection_receipt', 'prefix' => 'CR-', 'suffix' => '', 'current_number' => 0, 'padding' => 6,
        'reset_rule' => 'fiscal_year', 'active' => true, 'created_by' => $user->id, 'updated_by' => $user->id]);

    return compact('user', 'customer', 'method', 'period');
}

function paymentData(array $fixture, string $gross = '1000.0000'): array
{
    return ['customer_id' => $fixture['customer']->id, 'payment_method_id' => $fixture['method']->id,
        'payment_date' => '2026-07-17', 'reference_number' => 'DEP-100', 'gross_settlement_amount' => $gross,
        'withholding_amount' => '50.0000', 'other_deductions' => '20.0000',
        'net_cash_received' => bcsub($gross, '70.0000', 4)];
}

function postedInvoice(array $fixture, string $amount = '1000.0000'): SalesInvoice
{
    return SalesInvoice::factory()->for($fixture['customer'])->for($fixture['period'])->create([
        'invoice_number' => fake()->unique()->numerify('SI-######'), 'total_receivable' => $amount,
        'paid_amount' => '0.0000', 'balance_due' => $amount, 'status' => SalesInvoiceStatus::Posted,
        'posted_at' => now(), 'posted_by' => $fixture['user']->id,
    ]);
}

test('payment posts once and preserves separated settlement and unapplied amounts', function () {
    $fixture = paymentFixture();
    $this->actingAs($fixture['user'])->post(route('customer-payments.store'), paymentData($fixture))->assertRedirect();
    $payment = CustomerPayment::sole();
    expect($payment->status)->toBe(CustomerPaymentStatus::Draft)->and($payment->payment_number)->toBeNull()
        ->and($payment->gross_settlement_amount)->toBe('1000.0000')->and($payment->withholding_amount)->toBe('50.0000')
        ->and($payment->other_deductions)->toBe('20.0000')->and($payment->net_cash_received)->toBe('930.0000')
        ->and($payment->unapplied_amount)->toBe('1000.0000');
    $this->actingAs($fixture['user'])->patch(route('customer-payments.transition', $payment), ['status' => 'posted'])->assertSessionHasNoErrors();
    expect($payment->fresh()->status)->toBe(CustomerPaymentStatus::Posted)->and($payment->fresh()->payment_number)->toBe('CR-000001');
    $this->actingAs($fixture['user'])->patch(route('customer-payments.transition', $payment), ['status' => 'posted'])->assertSessionHasErrors('status');
});

test('one payment allocates to multiple invoices fully and partially in one transaction', function () {
    $fixture = paymentFixture();
    $first = postedInvoice($fixture, '600.0000');
    $second = postedInvoice($fixture, '800.0000');
    $payment = CustomerPayment::factory()->for($fixture['customer'])->for($fixture['method'])->create([
        'gross_settlement_amount' => '1000.0000', 'net_cash_received' => '1000.0000', 'unapplied_amount' => '1000.0000',
        'status' => CustomerPaymentStatus::Posted, 'created_by' => $fixture['user']->id, 'updated_by' => $fixture['user']->id]);
    $this->actingAs($fixture['user'])->post(route('customer-payments.allocate', $payment), ['allocations' => [
        ['sales_invoice_id' => $first->id, 'amount' => '600.0000'], ['sales_invoice_id' => $second->id, 'amount' => '400.0000'],
    ]])->assertSessionHasNoErrors();
    expect($first->fresh()->status)->toBe(SalesInvoiceStatus::Paid)->and($first->fresh()->balance_due)->toBe('0.0000')
        ->and($second->fresh()->status)->toBe(SalesInvoiceStatus::PartiallyPaid)->and($second->fresh()->balance_due)->toBe('400.0000')
        ->and($payment->fresh()->status)->toBe(CustomerPaymentStatus::FullyAllocated)->and($payment->fresh()->unapplied_amount)->toBe('0.0000');
});

test('multiple payments support partial allocation and advance unapplied balances', function () {
    $fixture = paymentFixture();
    $invoice = postedInvoice($fixture, '1000.0000');
    foreach (['300.0000', '200.0000'] as $amount) {
        $payment = CustomerPayment::factory()->for($fixture['customer'])->for($fixture['method'])->create([
            'gross_settlement_amount' => $amount, 'net_cash_received' => $amount, 'unapplied_amount' => $amount,
            'status' => CustomerPaymentStatus::Posted, 'created_by' => $fixture['user']->id, 'updated_by' => $fixture['user']->id]);
        $this->actingAs($fixture['user'])->post(route('customer-payments.allocate', $payment), ['allocations' => [['sales_invoice_id' => $invoice->id, 'amount' => $amount]]]);
    }
    $advance = CustomerPayment::factory()->for($fixture['customer'])->for($fixture['method'])->create([
        'gross_settlement_amount' => '250.0000', 'net_cash_received' => '250.0000', 'unapplied_amount' => '250.0000',
        'status' => CustomerPaymentStatus::Posted, 'created_by' => $fixture['user']->id, 'updated_by' => $fixture['user']->id]);
    expect($invoice->fresh()->paid_amount)->toBe('500.0000')->and($invoice->fresh()->balance_due)->toBe('500.0000')
        ->and($advance->unapplied_amount)->toBe('250.0000');
});

test('over allocation and cross customer allocation roll back all changes', function () {
    $fixture = paymentFixture();
    $invoice = postedInvoice($fixture, '500.0000');
    $otherInvoice = SalesInvoice::factory()->for($fixture['period'])->create(['status' => SalesInvoiceStatus::Posted, 'balance_due' => '100.0000']);
    $payment = CustomerPayment::factory()->for($fixture['customer'])->for($fixture['method'])->create([
        'gross_settlement_amount' => '600.0000', 'net_cash_received' => '600.0000', 'unapplied_amount' => '600.0000',
        'status' => CustomerPaymentStatus::Posted, 'created_by' => $fixture['user']->id, 'updated_by' => $fixture['user']->id]);
    $this->actingAs($fixture['user'])->post(route('customer-payments.allocate', $payment), ['allocations' => [
        ['sales_invoice_id' => $invoice->id, 'amount' => '400.0000'], ['sales_invoice_id' => $otherInvoice->id, 'amount' => '100.0000'],
    ]])->assertSessionHasErrors('allocations.1.sales_invoice_id');
    expect($invoice->fresh()->paid_amount)->toBe('0.0000')->and(PaymentAllocation::count())->toBe(0);
    $this->actingAs($fixture['user'])->post(route('customer-payments.allocate', $payment), ['allocations' => [['sales_invoice_id' => $invoice->id, 'amount' => '500.0001']]])->assertSessionHasErrors('allocations.0.amount');
});

test('voiding requires a reason and reverses active allocations', function () {
    $fixture = paymentFixture();
    $invoice = postedInvoice($fixture, '500.0000');
    $payment = CustomerPayment::factory()->for($fixture['customer'])->for($fixture['method'])->create([
        'gross_settlement_amount' => '500.0000', 'net_cash_received' => '500.0000', 'unapplied_amount' => '500.0000',
        'status' => CustomerPaymentStatus::Posted, 'created_by' => $fixture['user']->id, 'updated_by' => $fixture['user']->id]);
    $this->actingAs($fixture['user'])->post(route('customer-payments.allocate', $payment), ['allocations' => [['sales_invoice_id' => $invoice->id, 'amount' => '500.0000']]]);
    $this->actingAs($fixture['user'])->patch(route('customer-payments.transition', $payment), ['status' => 'voided'])->assertSessionHasErrors('reason');
    $this->actingAs($fixture['user'])->patch(route('customer-payments.transition', $payment), ['status' => 'voided', 'reason' => 'Deposit reversed'])->assertSessionHasNoErrors();
    expect($payment->fresh()->status)->toBe(CustomerPaymentStatus::Voided)->and($payment->fresh()->unapplied_amount)->toBe('500.0000')
        ->and($invoice->fresh()->paid_amount)->toBe('0.0000')->and($invoice->fresh()->balance_due)->toBe('500.0000')
        ->and(PaymentAllocation::sole()->status)->toBe(PaymentAllocationStatus::Reversed);
});

test('validation authorization and prohibited downstream effects are enforced', function () {
    $fixture = paymentFixture();
    $invalid = paymentData($fixture);
    $invalid['net_cash_received'] = '900.0000';
    $this->actingAs($fixture['user'])->post(route('customer-payments.store'), $invalid)->assertSessionHasErrors('gross_settlement_amount');
    $viewer = User::factory()->create();
    $viewer->assignRole('Viewer');
    $this->actingAs($viewer)->get(route('customer-payments.index'))->assertSuccessful();
    $this->actingAs($viewer)->post(route('customer-payments.store'), paymentData($fixture))->assertForbidden();
    expect(Schema::hasTable('journal_entries'))->toBeFalse()->and(Schema::hasTable('tax_returns'))->toBeFalse();
});
