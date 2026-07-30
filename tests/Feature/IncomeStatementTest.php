<?php

use App\Actions\SaveJournalEntry;
use App\Actions\TransitionJournalEntry;
use App\Enums\AccountClass;
use App\Enums\AccountType;
use App\Enums\JournalEntryStatus;
use App\Enums\NormalBalance;
use App\Models\Account;
use App\Models\FiscalPeriod;
use App\Models\FiscalYear;
use App\Models\JournalEntry;
use App\Models\User;
use App\Reports\IncomeStatementReport;
use App\Reports\TrialBalanceReport;
use App\Support\FinancialReportingConvention;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Spatie\Permission\Models\Permission;

uses(LazilyRefreshDatabase::class);

function incomeStatementContext(): array
{
    test()->seed([RolesAndPermissionsSeeder::class, ChartOfAccountsSeeder::class]);
    $user = User::factory()->create();
    $user->assignRole('Bookkeeper');
    $year = FiscalYear::factory()->create(['starts_on' => '2026-01-01', 'ends_on' => '2026-12-31']);
    $january = FiscalPeriod::factory()->create([
        'fiscal_year_id' => $year->id, 'name' => 'January 2026',
        'starts_on' => '2026-01-01', 'ends_on' => '2026-01-31',
        'calendar_year' => 2026, 'calendar_month' => 1, 'calendar_quarter' => 1, 'status' => 'open',
    ]);
    $july = FiscalPeriod::factory()->create([
        'fiscal_year_id' => $year->id, 'name' => 'July 2026',
        'starts_on' => '2026-07-01', 'ends_on' => '2026-07-31',
        'calendar_year' => 2026, 'calendar_month' => 7, 'calendar_quarter' => 3, 'status' => 'open',
    ]);
    $accounts = Account::query()->whereIn('code', ['1010', '4010', '4090', '5010', '6010', '4060', '6200'])
        ->get()->keyBy('code');
    $accounts['6990'] = Account::query()->create([
        'code' => '6990', 'name' => 'Other Expense', 'account_class' => AccountClass::OtherExpense,
        'account_type' => AccountType::OtherExpense, 'normal_balance' => NormalBalance::Debit,
    ]);

    return compact('user', 'year', 'january', 'july', 'accounts');
}

function saveIncomeJournal(array $context, string $number, string $date, array $lines, ?JournalEntryStatus $status = JournalEntryStatus::Posted): JournalEntry
{
    $entry = app(SaveJournalEntry::class)->handle([
        'journal_number' => $number, 'journal_date' => $date, 'document_date' => $date,
        'fiscal_period_id' => $date < '2026-07-01' ? $context['january']->id : $context['july']->id,
        'journal_type' => 'adjustment', 'source_type' => 'manual', 'reference_number' => $number,
        'description' => $number, 'lines' => $lines,
    ], $context['user']->id);

    return $status === null ? $entry : app(TransitionJournalEntry::class)->handle($entry, $status, $context['user']->id, $status === JournalEntryStatus::Voided ? 'Excluded test entry' : null);
}

function incomeStatementFilters(array $changes = []): array
{
    return array_replace([
        'start_date' => '2026-07-01', 'end_date' => '2026-07-31', 'as_of' => '2026-07-31',
        'fiscal_period_id' => null, 'report_view' => 'period', 'show_zero_balances' => false,
    ], $changes);
}

function postJulyIncomeActivity(array $context): void
{
    $accounts = $context['accounts'];
    saveIncomeJournal($context, 'IS-JULY', '2026-07-15', [
        ['account_id' => $accounts['1010']->id, 'debit' => '240.0000', 'credit' => '0.0000'],
        ['account_id' => $accounts['4090']->id, 'debit' => '100.0000', 'credit' => '0.0000'],
        ['account_id' => $accounts['5010']->id, 'debit' => '400.0000', 'credit' => '0.0000'],
        ['account_id' => $accounts['6010']->id, 'debit' => '200.0000', 'credit' => '0.0000'],
        ['account_id' => $accounts['6990']->id, 'debit' => '50.0000', 'credit' => '0.0000'],
        ['account_id' => $accounts['6200']->id, 'debit' => '30.0000', 'credit' => '0.0000'],
        ['account_id' => $accounts['4010']->id, 'debit' => '0.0000', 'credit' => '1000.0000'],
        ['account_id' => $accounts['4060']->id, 'debit' => '0.0000', 'credit' => '20.0000'],
    ]);
}

it('aggregates revenue expenses contra revenue gross profit and net income', function (): void {
    $context = incomeStatementContext();
    postJulyIncomeActivity($context);

    $statement = app(IncomeStatementReport::class)->generate(incomeStatementFilters());

    expect($statement['summary'])->toMatchArray([
        'revenue' => '1000.0000',
        'contra_revenue' => '100.0000',
        'net_sales' => '900.0000',
        'cost_of_sales' => '400.0000',
        'gross_profit' => '500.0000',
        'operating_expenses' => '200.0000',
        'operating_income' => '300.0000',
        'other_income' => '20.0000',
        'other_expenses' => '50.0000',
        'net_income_before_tax' => '270.0000',
        'income_tax' => '30.0000',
        'net_income_after_tax' => '240.0000',
    ])->and($statement['has_income_tax'])->toBeTrue()
        ->and($statement['reconciliation_difference'])->toBe('0.0000');
});

it('supports current-period and fiscal year-to-date behavior while excluding draft and voided entries', function (): void {
    $context = incomeStatementContext();
    postJulyIncomeActivity($context);
    saveIncomeJournal($context, 'IS-JAN', '2026-01-15', [
        ['account_id' => $context['accounts']['1010']->id, 'debit' => '400.0000', 'credit' => '0.0000'],
        ['account_id' => $context['accounts']['6010']->id, 'debit' => '100.0000', 'credit' => '0.0000'],
        ['account_id' => $context['accounts']['4010']->id, 'debit' => '0.0000', 'credit' => '500.0000'],
    ]);
    foreach ([null, JournalEntryStatus::Voided] as $status) {
        saveIncomeJournal($context, 'IS-EXCLUDE-'.($status?->value ?? 'draft'), '2026-07-20', [
            ['account_id' => $context['accounts']['1010']->id, 'debit' => '999.0000', 'credit' => '0.0000'],
            ['account_id' => $context['accounts']['4010']->id, 'debit' => '0.0000', 'credit' => '999.0000'],
        ], $status);
    }

    $period = app(IncomeStatementReport::class)->generate(incomeStatementFilters());
    $yearToDate = app(IncomeStatementReport::class)->generate(incomeStatementFilters(['start_date' => '2026-01-01', 'report_view' => 'year_to_date']));

    expect($period['summary']['net_income_after_tax'])->toBe('240.0000')
        ->and($yearToDate['summary']['net_income_after_tax'])->toBe('640.0000');

    $this->actingAs($context['user'])->get(route('income-statement.index', [
        'fiscal_period_id' => $context['july']->id, 'report_view' => 'year_to_date',
    ]))->assertSuccessful()->assertSee('2026-01-01')->assertSee('2026-07-31');
});

it('reconciles income-statement activity to the trial balance', function (): void {
    $context = incomeStatementContext();
    postJulyIncomeActivity($context);
    $statement = app(IncomeStatementReport::class)->generate(incomeStatementFilters());
    $trialBalance = app(TrialBalanceReport::class)->generate([
        'start_date' => '2026-07-01', 'end_date' => '2026-07-31', 'as_of' => '2026-07-31',
        'basis' => 'adjusted', 'account_id' => null, 'detail' => 'postable',
    ], false);
    $accountClasses = Account::query()->get(['id', 'account_class'])
        ->mapWithKeys(fn (Account $account): array => [$account->id => $account->account_class]);
    $trialNetIncome = $trialBalance['rows']
        ->filter(fn (array $row): bool => in_array($accountClasses[$row['account']->id], [
            AccountClass::Income, AccountClass::CostOfSales, AccountClass::Expense,
            AccountClass::OtherIncome, AccountClass::OtherExpense,
        ], true))
        ->reduce(function (string $total, array $row) use ($accountClasses): string {
            $income = in_array($accountClasses[$row['account']->id], [AccountClass::Income, AccountClass::OtherIncome], true);
            $movement = $income
                ? bcsub($row['movement_credit'], $row['movement_debit'], 4)
                : bcsub($row['movement_debit'], $row['movement_credit'], 4);

            return $income ? bcadd($total, $movement, 4) : bcsub($total, $movement, 4);
        }, '0.0000');

    expect($statement['summary']['net_income_after_tax'])->toBe($trialNetIncome);
});

it('provides hierarchy-aware drilldowns and zero-balance visibility', function (): void {
    $context = incomeStatementContext();
    postJulyIncomeActivity($context);
    $report = app(IncomeStatementReport::class);
    $hidden = $report->generate(incomeStatementFilters());
    $shown = $report->generate(incomeStatementFilters(['show_zero_balances' => true]));
    $revenueHeader = Account::query()->where('code', '4000')->sole();
    $drilldown = $report->drilldown(incomeStatementFilters(), $revenueHeader);

    expect($hidden['sections']['revenue']['rows']->pluck('account.code'))->not->toContain('4020')
        ->and($shown['sections']['revenue']['rows']->pluck('account.code'))->toContain('4020')
        ->and($drilldown['total'])->toBe('1000.0000')
        ->and($drilldown['rows'])->toHaveCount(1)
        ->and($drilldown['rows']->first()->account->code)->toBe('4010');

    $this->actingAs($context['user'])->get(route('income-statement.drilldown', [
        'account' => $revenueHeader, ...incomeStatementFilters(),
    ]))->assertSuccessful()->assertSee('IS-JULY');
});

it('seeds permissions and enforces view export drilldown and date validation', function (): void {
    $context = incomeStatementContext();
    expect(Permission::query()->whereIn('name', [
        'income-statement.view', 'income-statement.export', 'income-statement.drilldown',
    ])->count())->toBe(3);

    $none = User::factory()->create();
    $viewer = User::factory()->create();
    $viewer->givePermissionTo('income-statement.view');
    $revenue = $context['accounts']['4010'];

    $this->actingAs($none)->get(route('income-statement.index'))->assertForbidden();
    $this->actingAs($viewer)->get(route('income-statement.index'))->assertSuccessful();
    $this->actingAs($viewer)->get(route('income-statement.export'))->assertForbidden();
    $this->actingAs($viewer)->get(route('income-statement.drilldown', $revenue))->assertForbidden();
    $this->actingAs($context['user'])->get(route('income-statement.index', [
        'start_date' => '2026-07-31', 'end_date' => '2026-07-01',
    ]))->assertSessionHasErrors('end_date');
    $this->actingAs($context['user'])->get(route('income-statement.print', incomeStatementFilters()))
        ->assertSuccessful()->assertSee('Income Statement');
    $this->actingAs($context['user'])->get(route('income-statement.export', incomeStatementFilters()))
        ->assertSuccessful()->assertDownload('income-statement-2026-07-01-2026-07-31.csv');

    expect(FinancialReportingConvention::PERMISSIONS)->toContain(
        'income-statement.view', 'income-statement.export', 'income-statement.drilldown',
    );
});
