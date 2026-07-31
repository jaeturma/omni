<?php

use App\Enums\AccountClass;
use App\Enums\AccountType;
use App\Enums\CashFlowClassification;
use App\Enums\CurrentClassification;
use App\Enums\JournalEntryStatus;
use App\Enums\NormalBalance;
use App\Enums\ReportBalanceBasis;
use App\Models\Account;
use App\Models\User;
use App\Reports\CashFlowStatementReport;
use App\Support\FinancialReportingConvention;
use App\ValueObjects\FinancialReportParameters;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(LazilyRefreshDatabase::class);

it('defines posted journals, report bases, and normal-balance sign presentation', function (): void {
    expect(FinancialReportingConvention::SOURCE_STATUSES)->toBe([
        JournalEntryStatus::Posted, JournalEntryStatus::Reversed,
    ])->and(FinancialReportingConvention::balanceBasis(AccountClass::Income))->toBe(ReportBalanceBasis::PeriodActivity)
        ->and(FinancialReportingConvention::balanceBasis(AccountClass::Expense))->toBe(ReportBalanceBasis::PeriodActivity)
        ->and(FinancialReportingConvention::balanceBasis(AccountClass::Asset))->toBe(ReportBalanceBasis::Cumulative)
        ->and(FinancialReportingConvention::present('125.0000', '25.0000', NormalBalance::Debit))->toBe('100.0000')
        ->and(FinancialReportingConvention::present('25.0000', '125.0000', NormalBalance::Credit))->toBe('100.0000')
        ->and(FinancialReportingConvention::TRIAL_BALANCE_READINESS_RULE)->toBe('balanced_trial_balance_required');
});

it('presents contra accounts by their configured normal balance', function (): void {
    expect(AccountType::AccumulatedDepreciation->normalBalance())->toBe(NormalBalance::Credit)
        ->and(AccountType::SalesReturnsDiscounts->normalBalance())->toBe(NormalBalance::Debit)
        ->and(FinancialReportingConvention::present('0.0000', '750.0000', AccountType::AccumulatedDepreciation->normalBalance()))->toBe('750.0000')
        ->and(FinancialReportingConvention::present('125.0000', '0.0000', AccountType::SalesReturnsDiscounts->normalBalance()))->toBe('125.0000');
});

it('provides configurable current and cash-flow classifications with deterministic defaults', function (): void {
    $this->seed(ChartOfAccountsSeeder::class);

    $inventory = Account::query()->where('code', '1200')->firstOrFail();
    $equipment = Account::query()->where('code', '1500')->firstOrFail();
    $loan = Account::query()->where('code', '2100')->firstOrFail();

    expect($inventory->current_classification)->toBe(CurrentClassification::Current)
        ->and($inventory->cash_flow_classification)->toBe(CashFlowClassification::Operating)
        ->and($equipment->current_classification)->toBe(CurrentClassification::NonCurrent)
        ->and($equipment->cash_flow_classification)->toBe(CashFlowClassification::Investing)
        ->and($loan->cash_flow_classification)->toBe(CashFlowClassification::Financing);
});

it('requires reproducible primary and like-for-like comparative parameters', function (): void {
    $parameters = new FinancialReportParameters(
        '2026-07-01', '2026-07-31', '2026-07-31', 7,
        '2026-06-01', '2026-06-30', true,
    );

    expect($parameters->outputParameters())->toBe([
        'start_date' => '2026-07-01',
        'end_date' => '2026-07-31',
        'as_of_date' => '2026-07-31',
        'fiscal_period_id' => 7,
        'comparison_start_date' => '2026-06-01',
        'comparison_end_date' => '2026-06-30',
        'show_zero_balances' => true,
    ])->and(FinancialReportingConvention::COMPARATIVE_MAPPING_RULE)->toBe('same_account_mapping_and_basis')
        ->and(FinancialReportingConvention::OUTPUT_PARAMETER_RULE)->toBe('print_and_export_include_all_parameters');

    expect(fn () => new FinancialReportParameters(
        '2026-07-01', '2026-07-31', '2026-07-31', comparisonStartDate: '2026-06-01',
    ))->toThrow(DomainException::class, 'Both comparative-period dates are required');

    expect(fn () => new FinancialReportParameters(
        '2026-07-01', '2026-07-31', '2026-07-31',
        comparisonStartDate: '2026-06-15', comparisonEndDate: '2026-07-05',
    ))->toThrow(DomainException::class, 'must precede');
});

it('rounds decimal amounts half up and rounds subtotals from unrounded values', function (): void {
    expect(FinancialReportingConvention::ROUNDING_METHOD)->toBe('round_half_up')
        ->and(FinancialReportingConvention::round('1.0050'))->toBe('1.01')
        ->and(FinancialReportingConvention::round('-1.0050'))->toBe('-1.01')
        ->and(FinancialReportingConvention::subtotal(['0.0049', '0.0049']))->toBe('0.01')
        ->and(FinancialReportingConvention::ZERO_BALANCES_VISIBLE_BY_DEFAULT)->toBeFalse();
});

it('seeds phase eight permissions without creating stored financial-statement records', function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);

    expect(Permission::query()->whereIn('name', FinancialReportingConvention::PERMISSIONS)->count())
        ->toBe(count(FinancialReportingConvention::PERMISSIONS))
        ->and(Role::findByName('Administrator')->hasAllPermissions(FinancialReportingConvention::PERMISSIONS))->toBeTrue()
        ->and(Role::findByName('Owner')->hasAllPermissions(FinancialReportingConvention::PERMISSIONS))->toBeTrue()
        ->and(Role::findByName('Bookkeeper')->hasAllPermissions(FinancialReportingConvention::PERMISSIONS))->toBeTrue()
        ->and(Role::findByName('Viewer')->hasPermissionTo('financial-reports.view'))->toBeTrue()
        ->and(Role::findByName('Viewer')->hasPermissionTo('financial-reports.export'))->toBeFalse()
        ->and(Schema::hasTable('financial_statements'))->toBeFalse();

    expect(class_exists(CashFlowStatementReport::class))->toBeFalse();
});

it('requires the reporting-settings permission to change account classifications', function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    $account = Account::query()->create([
        'code' => '1505', 'name' => 'Test Equipment', 'account_class' => AccountClass::Asset,
        'account_type' => AccountType::PropertyPlantEquipment, 'normal_balance' => NormalBalance::Debit,
        'current_classification' => CurrentClassification::NonCurrent,
        'cash_flow_classification' => CashFlowClassification::Investing,
    ]);
    $user = User::factory()->create();
    $user->givePermissionTo(['chart-of-accounts.update']);

    $payload = [
        'code' => '1505', 'name' => 'Test Equipment', 'account_class' => AccountClass::Asset->value,
        'account_type' => AccountType::PropertyPlantEquipment->value, 'parent_id' => null,
        'is_header' => '0', 'is_postable' => '1', 'is_control_account' => '0',
        'control_account_type' => null, 'current_classification' => CurrentClassification::Current->value,
        'cash_flow_classification' => CashFlowClassification::Operating->value,
        'description' => null, 'display_order' => 0,
    ];

    $this->actingAs($user)->put(route('accounts.update', $account), $payload)
        ->assertSessionHasErrors(['current_classification', 'cash_flow_classification']);

    $user->givePermissionTo('financial-report-settings.manage');
    $this->actingAs($user)->put(route('accounts.update', $account), $payload)
        ->assertRedirect(route('accounts.index'));

    expect($account->fresh()->current_classification)->toBe(CurrentClassification::Current)
        ->and($account->fresh()->cash_flow_classification)->toBe(CashFlowClassification::Operating);
});

it('rejects current classification for non-balance-sheet accounts', function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    $user = User::factory()->create();
    $user->assignRole('Bookkeeper');

    $this->actingAs($user)->post(route('accounts.store'), [
        'code' => '4015', 'name' => 'Test Revenue', 'account_class' => AccountClass::Income->value,
        'account_type' => AccountType::SalesIncome->value, 'parent_id' => null,
        'is_header' => '0', 'is_postable' => '1', 'is_control_account' => '0',
        'control_account_type' => null, 'current_classification' => CurrentClassification::Current->value,
        'cash_flow_classification' => CashFlowClassification::Operating->value,
        'description' => null, 'display_order' => 0,
    ])->assertSessionHasErrors('current_classification');
});
