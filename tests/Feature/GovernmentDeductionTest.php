<?php

use App\Enums\CustomerPaymentStatus;
use App\Enums\GovernmentDeductionStatus;
use App\Enums\SalesInvoiceStatus;
use App\Models\BusinessProfile;
use App\Models\Customer;
use App\Models\CustomerPayment;
use App\Models\FiscalPeriod;
use App\Models\GovernmentDeduction;
use App\Models\PaymentMethod;
use App\Models\SalesInvoice;
use App\Models\TaxProfile;
use App\Models\TaxRateSetting;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(LazilyRefreshDatabase::class);
beforeEach(fn () => $this->seed(RolesAndPermissionsSeeder::class));

function governmentDeductionFixture(string $rate = '2.000000'): array
{
    $user = User::factory()->administrator()->create();
    $customer = Customer::factory()->create(['type' => 'government', 'name' => 'DepEd Division Office']);
    $business = BusinessProfile::factory()->active()->create();
    $profile = TaxProfile::create(['business_profile_id' => $business->id, 'taxpayer_type' => 'sole_proprietor', 'registration_type' => 'non_vat',
        'vat_status' => 'non_vat', 'income_tax_option' => 'graduated', 'percentage_tax_registered' => true, 'percentage_tax_rate' => '3.000000',
        'percentage_tax_effective_from' => '2026-01-01', 'filing_frequency' => 'quarterly', 'registration_start_date' => '2026-05-01',
        'first_filing_period' => '2026-Q2', 'rdo_code' => '001', 'tin' => '123-456-789', 'branch_code' => '000', 'registered_books_type' => 'manual', 'active' => true]);
    $taxRate = TaxRateSetting::create(['tax_profile_id' => $profile->id, 'tax_type' => 'expanded_withholding_tax', 'rate' => $rate,
        'effective_from' => '2026-01-01', 'effective_to' => null, 'active' => true]);
    $period = FiscalPeriod::factory()->create(['starts_on' => '2026-01-01', 'ends_on' => '2026-12-31', 'status' => 'open']);
    $invoice = SalesInvoice::factory()->for($customer)->for($period)->create(['invoice_number' => 'SI-GOV-001', 'invoice_date' => '2026-07-15',
        'due_date' => '2026-08-15', 'gross_amount' => '10000.0000', 'net_sales_amount' => '10000.0000', 'total_receivable' => '10000.0000',
        'balance_due' => '10000.0000', 'status' => SalesInvoiceStatus::Posted, 'posted_at' => now(), 'posted_by' => $user->id]);

    return compact('user', 'customer', 'taxRate', 'invoice');
}

function governmentDeductionData(array $fixture, array $overrides = []): array
{
    return array_replace(['sales_invoice_id' => $fixture['invoice']->id, 'tax_rate_setting_id' => $fixture['taxRate']->id,
        'deduction_type' => 'expanded_withholding_tax', 'certificate_type' => '2307', 'covered_from' => '2026-07-01',
        'covered_to' => '2026-09-30', 'gross_basis' => '10000.0000'], $overrides);
}

test('configured effective rate calculates deduction without changing invoice gross sales', function () {
    $fixture = governmentDeductionFixture('2.000000');
    $this->actingAs($fixture['user'])->post(route('government-deductions.store'), governmentDeductionData($fixture))->assertRedirect();
    $deduction = GovernmentDeduction::sole();
    expect($deduction->rate)->toBe('2.000000')->and($deduction->amount)->toBe('200.0000')
        ->and($deduction->gross_basis)->toBe('10000.0000')->and($deduction->status)->toBe(GovernmentDeductionStatus::Pending)
        ->and($fixture['invoice']->fresh()->gross_amount)->toBe('10000.0000');
});

test('rate must match deduction type and be effective on invoice date', function () {
    $fixture = governmentDeductionFixture();
    $fixture['taxRate']->update(['effective_from' => '2026-08-01']);
    $this->actingAs($fixture['user'])->post(route('government-deductions.store'), governmentDeductionData($fixture))
        ->assertSessionHasErrors('tax_rate_setting_id');
    expect(GovernmentDeduction::count())->toBe(0);
});

test('certificates are traceable missing documents are reported and duplicates are prevented', function () {
    $fixture = governmentDeductionFixture();
    $this->actingAs($fixture['user'])->post(route('government-deductions.store'), governmentDeductionData($fixture));
    $this->actingAs($fixture['user'])->post(route('government-deductions.store'), governmentDeductionData($fixture))
        ->assertSessionHasErrors('deduction_type');
    $this->actingAs($fixture['user'])->get(route('government-deductions.index', ['year' => 2026, 'quarter' => 3, 'missing_certificate' => 1]))
        ->assertSuccessful()->assertSee('SI-GOV-001')->assertSee('Missing');

    $deduction = GovernmentDeduction::sole();
    $this->actingAs($fixture['user'])->put(route('government-deductions.update', $deduction), governmentDeductionData($fixture, [
        'certificate_number' => '2307-2026-001', 'certificate_date' => '2026-09-30', 'attachment_reference' => 'files/2307-001.pdf',
    ]))->assertRedirect();
    expect($deduction->fresh()->status)->toBe(GovernmentDeductionStatus::Received)
        ->and($deduction->fresh()->attachment_reference)->toBe('files/2307-001.pdf');
});

test('related payment must be posted for the invoice customer', function () {
    $fixture = governmentDeductionFixture();
    $otherCustomer = Customer::factory()->create(['type' => 'government']);
    $payment = CustomerPayment::factory()->for($otherCustomer)->for(PaymentMethod::factory())->create(['status' => CustomerPaymentStatus::Posted]);
    $this->actingAs($fixture['user'])->post(route('government-deductions.store'), governmentDeductionData($fixture, ['customer_payment_id' => $payment->id]))
        ->assertSessionHasErrors('customer_payment_id');
});

test('quarterly and customer summaries preserve gross sales and compute net after deductions', function () {
    $fixture = governmentDeductionFixture();
    $this->actingAs($fixture['user'])->post(route('government-deductions.store'), governmentDeductionData($fixture));
    $response = $this->actingAs($fixture['user'])->get(route('government-deductions.index', ['year' => 2026, 'quarter' => 3, 'customer_id' => $fixture['customer']->id]));
    $response->assertSuccessful()->assertSee('10,000.00')->assertSee('200.00')->assertSee('9,800.00')->assertSee('DepEd Division Office');
});

test('received certificates can be verified and voiding requires a reason', function () {
    $fixture = governmentDeductionFixture();
    $this->actingAs($fixture['user'])->post(route('government-deductions.store'), governmentDeductionData($fixture, [
        'certificate_number' => 'CERT-001', 'certificate_date' => '2026-09-30',
    ]));
    $deduction = GovernmentDeduction::sole();
    $this->actingAs($fixture['user'])->patch(route('government-deductions.transition', $deduction), ['status' => 'verified'])->assertSessionHasNoErrors();
    expect($deduction->fresh()->status)->toBe(GovernmentDeductionStatus::Verified)->and($deduction->fresh()->verified_at)->not->toBeNull();
    $this->actingAs($fixture['user'])->patch(route('government-deductions.transition', $deduction), ['status' => 'voided'])->assertSessionHasErrors('reason');
    $this->actingAs($fixture['user'])->patch(route('government-deductions.transition', $deduction), ['status' => 'voided', 'reason' => 'Certificate cancelled'])->assertSessionHasNoErrors();
    expect($deduction->fresh()->status)->toBe(GovernmentDeductionStatus::Voided)->and($deduction->fresh()->void_reason)->toBe('Certificate cancelled');
});

test('authorization is enforced and no return or journal is generated', function () {
    $fixture = governmentDeductionFixture();
    $viewer = User::factory()->create();
    $viewer->assignRole('Viewer');
    $this->actingAs($viewer)->get(route('government-deductions.index'))->assertSuccessful();
    $this->actingAs($viewer)->post(route('government-deductions.store'), governmentDeductionData($fixture))->assertForbidden();
    $unauthorized = User::factory()->create();
    $this->actingAs($unauthorized)->get(route('government-deductions.index'))->assertForbidden();
    expect(Schema::hasTable('tax_returns'))->toBeFalse()->and(Schema::hasTable('journal_entries'))->toBeFalse();
});
