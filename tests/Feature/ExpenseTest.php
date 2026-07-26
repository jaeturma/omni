<?php

use App\Enums\ExpenseStatus;
use App\Models\BusinessProfile;
use App\Models\DocumentSequence;
use App\Models\Expense;
use App\Models\FiscalPeriod;
use App\Models\FiscalYear;
use App\Models\JournalEntry;
use App\Models\PaymentMethod;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(LazilyRefreshDatabase::class);
beforeEach(fn () => $this->seed(RolesAndPermissionsSeeder::class));

function expenseFixtures(): array
{
    $admin = User::factory()->administrator()->create();
    $method = PaymentMethod::factory()->for($admin, 'creator')->for($admin, 'updater')->create();
    $business = BusinessProfile::factory()->active()->create();
    $year = FiscalYear::factory()->for($business)->for($admin, 'creator')->create(['starts_on' => '2026-01-01', 'ends_on' => '2026-12-31']);
    $period = FiscalPeriod::factory()->for($year)->create(['name' => 'July 2026', 'starts_on' => '2026-07-01', 'ends_on' => '2026-07-31', 'calendar_year' => 2026, 'calendar_month' => 7, 'calendar_quarter' => 3, 'status' => 'open']);
    DocumentSequence::query()->create(['business_profile_id' => $business->id, 'fiscal_year_id' => $year->id, 'fiscal_year_scope' => $year->id, 'document_type' => 'expense_voucher', 'prefix' => 'EV-{YYYY}-', 'suffix' => '', 'current_number' => 0, 'padding' => 6, 'reset_rule' => 'fiscal_year', 'active' => true, 'created_by' => $admin->id, 'updated_by' => $admin->id]);

    return compact('admin', 'method', 'period');
}

function expenseData(array $f, array $changes = []): array
{
    return array_replace(['fiscal_period_id' => $f['period']->id, 'expense_date' => '2026-07-18', 'payee_name' => 'Manila Electric Company', 'expense_category' => 'utilities', 'description' => 'Office electricity', 'business_purpose' => 'Electricity used for daily business operations', 'reference_number' => 'BILL-100', 'receipt_available' => true, 'receipt_reference' => 'OR-100', 'gross_amount' => '1000.0000', 'withholding_amount' => '50.0000', 'other_deductions' => '20.0000', 'net_cash_paid' => '0.0000'], $changes);
}

function createExpense($test, array $f, array $changes = []): Expense
{
    $test->actingAs($f['admin'])->post(route('expenses.store'), expenseData($f, $changes))->assertRedirect();

    return Expense::query()->latest('id')->firstOrFail();
}

test('direct paid expense issues one number and keeps settlement components separate', function () {
    $f = expenseFixtures();
    $expense = createExpense($this, $f);
    $this->patch(route('expenses.transition', $expense), ['status' => 'paid', 'payment_method_id' => $f['method']->id, 'withholding_amount' => '50.0000', 'other_deductions' => '20.0000', 'net_cash_paid' => '930.0000'])->assertSessionHasNoErrors();
    expect($expense->fresh()->status)->toBe(ExpenseStatus::Paid)->and($expense->fresh()->expense_number)->toBe('EV-2026-000001')->and($expense->fresh()->gross_amount)->toBe('1000.0000')->and($expense->fresh()->withholding_amount)->toBe('50.0000')->and($expense->fresh()->other_deductions)->toBe('20.0000')->and($expense->fresh()->net_cash_paid)->toBe('930.0000');
    $this->patch(route('expenses.transition', $expense), ['status' => 'paid'])->assertForbidden();
});

test('approved unpaid and reimbursable expense claims remain unpaid', function () {
    $f = expenseFixtures();
    $unpaid = createExpense($this, $f);
    $this->patch(route('expenses.transition', $unpaid), ['status' => 'approved'])->assertSessionHasNoErrors();
    $claim = createExpense($this, $f, ['reference_number' => 'CLAIM-1', 'reimbursable' => true]);
    $this->patch(route('expenses.transition', $claim), ['status' => 'reimbursable'])->assertSessionHasNoErrors();
    expect($unpaid->fresh()->status)->toBe(ExpenseStatus::Approved)->and($unpaid->fresh()->net_cash_paid)->toBe('0.0000')->and($claim->fresh()->status)->toBe(ExpenseStatus::Reimbursable)->and($claim->fresh()->reimbursable)->toBeTrue()->and($claim->fresh()->net_cash_paid)->toBe('0.0000');
});

test('owner drawings are excluded from operating expense categories', function () {
    $f = expenseFixtures();
    $this->actingAs($f['admin'])->post(route('expenses.store'), expenseData($f, ['expense_category' => 'owner_drawing']))->assertSessionHasErrors('expense_category');
    expect(Expense::query()->count())->toBe(0);
});

test('business purpose and positive amounts are required', function () {
    $f = expenseFixtures();
    $this->actingAs($f['admin'])->post(route('expenses.store'), expenseData($f, ['business_purpose' => '', 'gross_amount' => '0']))->assertSessionHasErrors(['business_purpose', 'gross_amount']);
    $expense = createExpense($this, $f);
    $this->patch(route('expenses.transition', $expense), ['status' => 'paid', 'payment_method_id' => $f['method']->id, 'withholding_amount' => '50.0000', 'other_deductions' => '20.0000', 'net_cash_paid' => '900.0000'])->assertSessionHasErrors('gross_amount');
    expect($expense->fresh()->status)->toBe(ExpenseStatus::Draft);
});

test('expense access and transitions are authorized and printable', function () {
    $f = expenseFixtures();
    $expense = createExpense($this, $f);
    $viewer = User::factory()->create();
    $viewer->assignRole('Viewer');
    $this->actingAs($viewer)->get(route('expenses.index'))->assertSuccessful();
    $this->get(route('expenses.print', $expense))->assertSuccessful()->assertSee('Office electricity');
    $this->post(route('expenses.store'), expenseData($f))->assertForbidden();
    $this->patch(route('expenses.transition', $expense), ['status' => 'approved'])->assertForbidden();
});

test('voiding requires a reason and preserves the expense audit record', function () {
    $f = expenseFixtures();
    $expense = createExpense($this, $f);
    $this->patch(route('expenses.transition', $expense), ['status' => 'approved']);
    $this->patch(route('expenses.transition', $expense), ['status' => 'voided'])->assertSessionHasErrors('reason');
    $this->patch(route('expenses.transition', $expense), ['status' => 'voided', 'reason' => 'Duplicate expense claim'])->assertSessionHasNoErrors();
    expect($expense->fresh()->status)->toBe(ExpenseStatus::Voided)->and($expense->fresh()->void_reason)->toBe('Duplicate expense claim')->and(Expense::query()->count())->toBe(1);
});

test('expenses create no accounting payroll depreciation or tax return effects', function () {
    $f = expenseFixtures();
    createExpense($this, $f);
    expect(JournalEntry::query()->count())->toBe(0)->and(Schema::hasTable('payroll_runs'))->toBeFalse()->and(Schema::hasTable('depreciation_entries'))->toBeFalse()->and(Schema::hasTable('tax_returns'))->toBeFalse();
});
