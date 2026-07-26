<?php

use App\Enums\CashDisbursementStatus;
use App\Models\BusinessProfile;
use App\Models\CashDisbursement;
use App\Models\DocumentSequence;
use App\Models\Expense;
use App\Models\FinancialAccount;
use App\Models\FiscalPeriod;
use App\Models\FiscalYear;
use App\Models\JournalEntry;
use App\Models\PaymentMethod;
use App\Models\Supplier;
use App\Models\SupplierPayment;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(LazilyRefreshDatabase::class);
beforeEach(fn () => $this->seed(RolesAndPermissionsSeeder::class));

function cashDisbursementFixture(): array
{
    $user = User::factory()->administrator()->create();
    $business = BusinessProfile::factory()->active()->create();
    $year = FiscalYear::factory()->for($business)->for($user, 'creator')->create(['starts_on' => '2026-01-01', 'ends_on' => '2026-12-31']);
    $period = FiscalPeriod::factory()->for($year)->create(['name' => 'July 2026', 'starts_on' => '2026-07-01', 'ends_on' => '2026-07-31', 'status' => 'open']);
    DocumentSequence::query()->create([
        'business_profile_id' => $business->id, 'fiscal_year_id' => $year->id, 'fiscal_year_scope' => $year->id,
        'document_type' => 'cash_disbursement', 'prefix' => 'CD-', 'suffix' => '', 'current_number' => 0, 'padding' => 6,
        'reset_rule' => 'fiscal_year', 'active' => true, 'created_by' => $user->id, 'updated_by' => $user->id,
    ]);
    $account = FinancialAccount::factory()->create(['opening_balance' => '5000.0000', 'current_balance' => null, 'allow_disbursements' => true]);
    $method = PaymentMethod::factory()->create();

    return compact('user', 'period', 'account', 'method');
}

function disbursementData(array $fixture, array $changes = []): array
{
    return array_replace([
        'disbursement_date' => '2026-07-24', 'fiscal_period_id' => $fixture['period']->id,
        'financial_account_id' => $fixture['account']->id, 'source_type' => 'other_approved',
        'payee' => 'Approved payee', 'payment_method_id' => $fixture['method']->id,
        'reference_number' => 'CHK-100', 'gross_settlement' => '1000.0000',
        'deductions_or_bank_charges' => '25.0000', 'net_cash_out' => '975.0000',
    ], $changes);
}

test('approved manual disbursement posts with controlled number and reduces balance', function () {
    $fixture = cashDisbursementFixture();
    $this->actingAs($fixture['user'])->post(route('cash-disbursements.store'), disbursementData($fixture))->assertRedirect();
    $disbursement = CashDisbursement::sole();
    expect($disbursement->status)->toBe(CashDisbursementStatus::Draft)->and($fixture['account']->fresh()->current_balance)->toBeNull();

    $this->patch(route('cash-disbursements.transition', $disbursement), ['status' => 'posted'])->assertSessionHasNoErrors();
    expect($disbursement->fresh()->disbursement_number)->toBe('CD-000001')
        ->and($disbursement->fresh()->status)->toBe(CashDisbursementStatus::Posted)
        ->and($fixture['account']->fresh()->current_balance)->toBe('4025.0000');
});

test('supplier payment linkage is preserved and duplicate source is prevented', function () {
    $fixture = cashDisbursementFixture();
    $supplier = Supplier::factory()->create();
    $payment = SupplierPayment::query()->create([
        'supplier_id' => $supplier->id, 'payment_method_id' => $fixture['method']->id,
        'payment_number' => 'SP-100', 'payment_date' => '2026-07-24', 'status' => 'posted', 'gross_settlement_amount' => '1000.0000',
        'withholding_amount' => '50.0000', 'other_deductions' => '20.0000', 'net_cash_paid' => '930.0000',
        'unapplied_amount' => '1000.0000', 'created_by' => $fixture['user']->id, 'updated_by' => $fixture['user']->id,
    ]);
    $data = disbursementData($fixture, [
        'source_type' => 'supplier_payment', 'supplier_payment_id' => $payment->id, 'payee' => $supplier->name,
        'gross_settlement' => '1000.0000', 'deductions_or_bank_charges' => '70.0000', 'net_cash_out' => '930.0000',
    ]);

    $this->actingAs($fixture['user'])->post(route('cash-disbursements.store'), $data)->assertSessionHasNoErrors();
    expect(CashDisbursement::sole()->supplierPayment->is($payment))->toBeTrue();
    $this->post(route('cash-disbursements.store'), $data)->assertSessionHasErrors('supplier_payment_id');
    expect(CashDisbursement::count())->toBe(1);
});

test('approved expense linkage is preserved and validated', function () {
    $fixture = cashDisbursementFixture();
    $expense = Expense::query()->create([
        'fiscal_period_id' => $fixture['period']->id, 'expense_date' => '2026-07-24',
        'status' => 'approved', 'payee_name' => 'Utility Provider', 'expense_category' => 'utilities',
        'description' => 'Electric service', 'business_purpose' => 'Office electricity', 'gross_amount' => '500.0000',
        'withholding_amount' => '10.0000', 'other_deductions' => '0.0000', 'net_cash_paid' => '490.0000',
        'created_by' => $fixture['user']->id, 'updated_by' => $fixture['user']->id,
    ]);
    $data = disbursementData($fixture, [
        'source_type' => 'expense', 'expense_id' => $expense->id, 'payee' => 'Utility Provider',
        'gross_settlement' => '500.0000', 'deductions_or_bank_charges' => '10.0000', 'net_cash_out' => '490.0000',
    ]);

    $this->actingAs($fixture['user'])->post(route('cash-disbursements.store'), $data)->assertSessionHasNoErrors();
    expect(CashDisbursement::sole()->expense->is($expense))->toBeTrue();
});

test('source eligibility is rechecked when a draft is posted', function () {
    $fixture = cashDisbursementFixture();
    $supplier = Supplier::factory()->create();
    $payment = SupplierPayment::query()->create([
        'supplier_id' => $supplier->id, 'payment_method_id' => $fixture['method']->id,
        'payment_number' => 'SP-101', 'payment_date' => '2026-07-24', 'status' => 'posted',
        'gross_settlement_amount' => '1000.0000', 'net_cash_paid' => '1000.0000',
        'unapplied_amount' => '1000.0000', 'created_by' => $fixture['user']->id, 'updated_by' => $fixture['user']->id,
    ]);
    $data = disbursementData($fixture, [
        'source_type' => 'supplier_payment', 'supplier_payment_id' => $payment->id,
        'payee' => $supplier->name, 'deductions_or_bank_charges' => '0.0000', 'net_cash_out' => '1000.0000',
    ]);
    $this->actingAs($fixture['user'])->post(route('cash-disbursements.store'), $data)->assertSessionHasNoErrors();
    $payment->update(['status' => 'voided']);

    $this->patch(route('cash-disbursements.transition', CashDisbursement::sole()), ['status' => 'posted'])
        ->assertSessionHasErrors('supplier_payment_id');
    expect(CashDisbursement::sole()->status)->toBe(CashDisbursementStatus::Draft)
        ->and($fixture['account']->fresh()->current_balance)->toBeNull();
});

test('cheque release clearing and stop lifecycle records dates and restores balance once', function () {
    $fixture = cashDisbursementFixture();
    $this->actingAs($fixture['user'])->post(route('cash-disbursements.store'), disbursementData($fixture));
    $disbursement = CashDisbursement::sole();
    $this->patch(route('cash-disbursements.transition', $disbursement), ['status' => 'posted']);
    $this->patch(route('cash-disbursements.transition', $disbursement), ['status' => 'released', 'release_date' => '2026-07-25'])->assertSessionHasNoErrors();
    $this->patch(route('cash-disbursements.transition', $disbursement), ['status' => 'cleared', 'clearing_date' => '2026-07-26'])->assertSessionHasNoErrors();
    expect($disbursement->fresh()->release_date->toDateString())->toBe('2026-07-25')
        ->and($disbursement->fresh()->clearing_date->toDateString())->toBe('2026-07-26');

    $this->patch(route('cash-disbursements.transition', $disbursement), ['status' => 'stopped'])->assertSessionHasErrors('reason');
    $this->patch(route('cash-disbursements.transition', $disbursement), ['status' => 'stopped', 'reason' => 'Cheque stopped'])->assertSessionHasNoErrors();
    expect($disbursement->fresh()->status)->toBe(CashDisbursementStatus::Stopped)
        ->and($fixture['account']->fresh()->current_balance)->toBe('5000.0000');
    $this->patch(route('cash-disbursements.transition', $disbursement), ['status' => 'voided', 'reason' => 'Again'])->assertSessionHasErrors('status');
    expect($fixture['account']->fresh()->current_balance)->toBe('5000.0000');
});

test('voiding requires a reason and authorization is enforced', function () {
    $fixture = cashDisbursementFixture();
    $this->actingAs($fixture['user'])->post(route('cash-disbursements.store'), disbursementData($fixture));
    $disbursement = CashDisbursement::sole();
    $this->patch(route('cash-disbursements.transition', $disbursement), ['status' => 'posted']);
    $this->patch(route('cash-disbursements.transition', $disbursement), ['status' => 'voided'])->assertSessionHasErrors('reason');
    $this->patch(route('cash-disbursements.transition', $disbursement), ['status' => 'voided', 'reason' => 'Duplicate payment'])->assertSessionHasNoErrors();
    expect($disbursement->fresh()->void_reason)->toBe('Duplicate payment')
        ->and($fixture['account']->fresh()->current_balance)->toBe('5000.0000');

    $viewer = User::factory()->create();
    $viewer->assignRole('Viewer');
    $this->actingAs($viewer)->get(route('cash-disbursements.index'))->assertOk();
    $this->post(route('cash-disbursements.store'), disbursementData($fixture))->assertForbidden();
});

test('decimal validation and downstream boundaries are enforced', function () {
    $fixture = cashDisbursementFixture();
    $this->actingAs($fixture['user'])->post(route('cash-disbursements.store'), disbursementData($fixture, ['net_cash_out' => '900.0000']))
        ->assertSessionHasErrors('net_cash_out');
    expect(JournalEntry::query()->count())->toBe(0)
        ->and(Schema::hasTable('bank_reconciliations'))->toBeTrue();
});
