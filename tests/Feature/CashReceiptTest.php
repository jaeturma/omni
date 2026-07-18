<?php

use App\Enums\CashReceiptStatus;
use App\Models\BusinessProfile;
use App\Models\CashReceipt;
use App\Models\Customer;
use App\Models\CustomerPayment;
use App\Models\DocumentSequence;
use App\Models\FinancialAccount;
use App\Models\FiscalPeriod;
use App\Models\FiscalYear;
use App\Models\PaymentMethod;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(LazilyRefreshDatabase::class);
beforeEach(fn () => $this->seed(RolesAndPermissionsSeeder::class));

function cashReceiptFixture(): array
{
    $user = User::factory()->administrator()->create();
    $business = BusinessProfile::factory()->active()->create();
    $year = FiscalYear::factory()->for($business)->for($user, 'creator')->create(['starts_on' => '2026-01-01', 'ends_on' => '2026-12-31']);
    $period = FiscalPeriod::factory()->for($year)->create(['name' => 'July 2026', 'starts_on' => '2026-07-01', 'ends_on' => '2026-07-31', 'status' => 'open']);
    DocumentSequence::query()->create(['business_profile_id' => $business->id, 'fiscal_year_id' => $year->id, 'fiscal_year_scope' => $year->id,
        'document_type' => 'cash_receipt', 'prefix' => 'CR-', 'suffix' => '', 'current_number' => 0, 'padding' => 6,
        'reset_rule' => 'fiscal_year', 'active' => true, 'created_by' => $user->id, 'updated_by' => $user->id]);
    $account = FinancialAccount::factory()->create(['opening_balance' => '500.0000', 'current_balance' => null, 'allow_receipts' => true]);
    $customer = Customer::factory()->create();
    $method = PaymentMethod::factory()->create();

    return compact('user', 'period', 'account', 'customer', 'method');
}

function receiptData(array $f, array $changes = []): array
{
    return array_replace(['receipt_date' => '2026-07-18', 'fiscal_period_id' => $f['period']->id, 'financial_account_id' => $f['account']->id,
        'source_type' => 'other_income', 'payer_name' => 'Walk-in client', 'payment_method_id' => $f['method']->id,
        'reference_number' => 'REF-100', 'gross_receipt' => '1000.0000', 'deductions_or_fees' => '25.0000',
        'net_amount_deposited' => '975.0000'], $changes);
}

test('manual other income posts with controlled number and updates operational balance', function () {
    $f = cashReceiptFixture();
    $this->actingAs($f['user'])->post(route('cash-receipts.store'), receiptData($f))->assertRedirect();
    $receipt = CashReceipt::sole();
    expect($receipt->status)->toBe(CashReceiptStatus::Draft)->and($f['account']->fresh()->current_balance)->toBeNull();
    $this->patch(route('cash-receipts.transition', $receipt), ['status' => 'posted'])->assertSessionHasNoErrors();
    expect($receipt->fresh()->receipt_number)->toBe('CR-000001')->and($receipt->fresh()->status)->toBe(CashReceiptStatus::Posted)
        ->and($f['account']->fresh()->current_balance)->toBe('1475.0000');
    $this->patch(route('cash-receipts.transition', $receipt), ['status' => 'posted'])->assertSessionHasErrors('status');
});

test('customer payment linkage preserves source and prevents duplicate receipt', function () {
    $f = cashReceiptFixture();
    $payment = CustomerPayment::factory()->for($f['customer'])->for($f['method'])->create(['payment_number' => 'CP-100', 'status' => 'posted',
        'gross_settlement_amount' => '1000.0000', 'withholding_amount' => '50.0000', 'other_deductions' => '20.0000',
        'net_cash_received' => '930.0000', 'unapplied_amount' => '1000.0000']);
    $data = receiptData($f, ['source_type' => 'customer_payment', 'customer_id' => $f['customer']->id, 'customer_payment_id' => $payment->id,
        'payer_name' => $f['customer']->name, 'gross_receipt' => '1000.0000', 'deductions_or_fees' => '70.0000', 'net_amount_deposited' => '930.0000']);
    $this->actingAs($f['user'])->post(route('cash-receipts.store'), $data)->assertSessionHasNoErrors();
    expect(CashReceipt::sole()->customerPayment->is($payment))->toBeTrue();
    $this->post(route('cash-receipts.store'), $data)->assertSessionHasErrors('customer_payment_id');
    expect(CashReceipt::count())->toBe(1);
});

test('clearing and bounced handling retain audit and roll back balance once', function () {
    $f = cashReceiptFixture();
    $receipt = CashReceipt::factory()->create(receiptData($f, ['status' => 'posted', 'net_amount_deposited' => '975.0000', 'created_by' => $f['user']->id, 'updated_by' => $f['user']->id]));
    $f['account']->update(['current_balance' => '1475.0000']);
    $this->actingAs($f['user'])->patch(route('cash-receipts.transition', $receipt), ['status' => 'cleared', 'clearing_date' => '2026-07-19'])->assertSessionHasNoErrors();
    expect($receipt->fresh()->status)->toBe(CashReceiptStatus::Cleared)->and($receipt->fresh()->cleared_by)->toBe($f['user']->id);
    $this->patch(route('cash-receipts.transition', $receipt), ['status' => 'bounced'])->assertSessionHasErrors('reason');
    $this->patch(route('cash-receipts.transition', $receipt), ['status' => 'bounced', 'reason' => 'Returned check'])->assertSessionHasNoErrors();
    expect($receipt->fresh()->status)->toBe(CashReceiptStatus::Bounced)->and($receipt->fresh()->bounce_reason)->toBe('Returned check')
        ->and($f['account']->fresh()->current_balance)->toBe('500.0000');
    $this->patch(route('cash-receipts.transition', $receipt), ['status' => 'voided', 'reason' => 'Again'])->assertSessionHasErrors('status');
    expect($f['account']->fresh()->current_balance)->toBe('500.0000');
});

test('voiding posted receipt requires reason and reverses account balance', function () {
    $f = cashReceiptFixture();
    $this->actingAs($f['user'])->post(route('cash-receipts.store'), receiptData($f));
    $receipt = CashReceipt::sole();
    $this->patch(route('cash-receipts.transition', $receipt), ['status' => 'posted']);
    $this->patch(route('cash-receipts.transition', $receipt), ['status' => 'voided'])->assertSessionHasErrors('reason');
    $this->patch(route('cash-receipts.transition', $receipt), ['status' => 'voided', 'reason' => 'Duplicate bank advice'])->assertSessionHasNoErrors();
    expect($receipt->fresh()->status)->toBe(CashReceiptStatus::Voided)->and($receipt->fresh()->voided_by)->toBe($f['user']->id)
        ->and($f['account']->fresh()->current_balance)->toBe('500.0000');
});

test('validation authorization and downstream boundaries are enforced', function () {
    $f = cashReceiptFixture();
    $this->actingAs($f['user'])->post(route('cash-receipts.store'), receiptData($f, ['net_amount_deposited' => '900.0000']))->assertSessionHasErrors('net_amount_deposited');
    $viewer = User::factory()->create();
    $viewer->assignRole('Viewer');
    $this->actingAs($viewer)->get(route('cash-receipts.index'))->assertOk();
    $this->post(route('cash-receipts.store'), receiptData($f))->assertForbidden();
    expect(Schema::hasTable('journal_entries'))->toBeFalse()->and(Schema::hasTable('bank_reconciliations'))->toBeFalse();
});
