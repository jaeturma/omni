<?php

use App\Actions\SaveJournalEntry;
use App\Actions\TransitionJournalEntry;
use App\Enums\JournalEntryStatus;
use App\Models\Account;
use App\Models\FiscalPeriod;
use App\Models\FiscalYear;
use App\Models\JournalEntry;
use App\Models\User;
use App\Reports\BalanceSheetReport;
use App\Reports\IncomeStatementReport;
use App\Reports\OwnerEquityStatementReport;
use App\Support\FinancialReportingConvention;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Spatie\Permission\Models\Permission;

uses(LazilyRefreshDatabase::class);

function ownerEquityContext(): array
{
    test()->seed([RolesAndPermissionsSeeder::class, ChartOfAccountsSeeder::class]);
    $user = User::factory()->create();
    $user->assignRole('Bookkeeper');
    $priorYear = FiscalYear::factory()->create([
        'name' => 'FY 2025', 'starts_on' => '2025-01-01', 'ends_on' => '2025-12-31', 'is_current' => false,
    ]);
    $priorPeriod = FiscalPeriod::factory()->create([
        'fiscal_year_id' => $priorYear->id, 'name' => 'December 2025',
        'starts_on' => '2025-12-01', 'ends_on' => '2025-12-31',
        'calendar_year' => 2025, 'calendar_month' => 12, 'calendar_quarter' => 4, 'status' => 'open',
    ]);
    $year = FiscalYear::factory()->create([
        'name' => 'FY 2026', 'starts_on' => '2026-01-01', 'ends_on' => '2026-12-31', 'is_current' => true,
    ]);
    $period = FiscalPeriod::factory()->create([
        'fiscal_year_id' => $year->id, 'name' => 'June 2026',
        'starts_on' => '2026-06-01', 'ends_on' => '2026-06-30',
        'calendar_year' => 2026, 'calendar_month' => 6, 'calendar_quarter' => 2, 'status' => 'open',
    ]);
    $accounts = Account::query()->whereIn('code', [
        '1010', '1100', '3010', '3020', '3040', '4010', '6010',
    ])->get()->keyBy('code');

    return compact('user', 'priorPeriod', 'period', 'accounts');
}

function saveOwnerEquityJournal(
    array $context,
    string $number,
    string $date,
    array $lines,
    ?JournalEntryStatus $status = JournalEntryStatus::Posted,
): JournalEntry {
    $entry = app(SaveJournalEntry::class)->handle([
        'journal_number' => $number, 'journal_date' => $date, 'document_date' => $date,
        'fiscal_period_id' => $date < '2026-01-01' ? $context['priorPeriod']->id : $context['period']->id,
        'journal_type' => $date < '2026-01-01' ? 'opening' : 'adjustment',
        'source_type' => 'manual', 'reference_number' => $number, 'description' => $number, 'lines' => $lines,
    ], $context['user']->id);

    return $status === null
        ? $entry
        : app(TransitionJournalEntry::class)->handle($entry, $status, $context['user']->id);
}

function ownerEquityFilters(array $changes = []): array
{
    return array_replace([
        'start_date' => '2026-06-01', 'end_date' => '2026-06-30',
        'as_of' => '2026-06-30', 'fiscal_period_id' => null,
    ], $changes);
}

function postOwnerEquityActivity(array $context): void
{
    $account = $context['accounts'];
    saveOwnerEquityJournal($context, 'OE-OPEN', '2025-12-31', [
        ['account_id' => $account['1010']->id, 'debit' => '1000.0000', 'credit' => '0.0000'],
        ['account_id' => $account['3010']->id, 'debit' => '0.0000', 'credit' => '1000.0000'],
    ]);
    saveOwnerEquityJournal($context, 'OE-SALE', '2026-06-05', [
        ['account_id' => $account['1010']->id, 'debit' => '300.0000', 'credit' => '0.0000'],
        ['account_id' => $account['4010']->id, 'debit' => '0.0000', 'credit' => '300.0000'],
    ]);
    saveOwnerEquityJournal($context, 'OE-EXPENSE', '2026-06-10', [
        ['account_id' => $account['6010']->id, 'debit' => '100.0000', 'credit' => '0.0000'],
        ['account_id' => $account['1010']->id, 'debit' => '0.0000', 'credit' => '100.0000'],
    ]);
    saveOwnerEquityJournal($context, 'OE-CAPITAL', '2026-06-15', [
        ['account_id' => $account['1010']->id, 'debit' => '200.0000', 'credit' => '0.0000'],
        ['account_id' => $account['3010']->id, 'debit' => '0.0000', 'credit' => '200.0000'],
    ]);
    saveOwnerEquityJournal($context, 'OE-DRAW', '2026-06-20', [
        ['account_id' => $account['3020']->id, 'debit' => '50.0000', 'credit' => '0.0000'],
        ['account_id' => $account['1010']->id, 'debit' => '0.0000', 'credit' => '50.0000'],
    ]);
    saveOwnerEquityJournal($context, 'OE-PRIOR', '2026-06-25', [
        ['account_id' => $account['1100']->id, 'debit' => '25.0000', 'credit' => '0.0000'],
        ['account_id' => $account['3040']->id, 'debit' => '0.0000', 'credit' => '25.0000'],
    ]);
}

it('reports beginning capital contributions income drawings adjustments and closing equity', function (): void {
    $context = ownerEquityContext();
    postOwnerEquityActivity($context);
    $statement = app(OwnerEquityStatementReport::class)->generate(ownerEquityFilters());

    expect($statement['summary'])->toMatchArray([
        'beginning_equity' => '1000.0000',
        'contributions' => '200.0000',
        'net_income' => '200.0000',
        'drawings' => '-50.0000',
        'prior_period_adjustments' => '25.0000',
        'closing_equity' => '1375.0000',
        'balance_sheet_closing_equity' => '1375.0000',
        'reconciliation_difference' => '0.0000',
    ])->and($statement['net_income_reconciliation_difference'])->toBe('0.0000')
        ->and($statement['reconciled'])->toBeTrue()
        ->and($statement['final_ready'])->toBeTrue();
});

it('reconciles net income and closing equity to their source statements', function (): void {
    $context = ownerEquityContext();
    postOwnerEquityActivity($context);
    saveOwnerEquityJournal($context, 'OE-DRAFT', '2026-06-28', [
        ['account_id' => $context['accounts']['1010']->id, 'debit' => '999.0000', 'credit' => '0.0000'],
        ['account_id' => $context['accounts']['3010']->id, 'debit' => '0.0000', 'credit' => '999.0000'],
    ], null);
    $equity = app(OwnerEquityStatementReport::class)->generate(ownerEquityFilters());
    $income = app(IncomeStatementReport::class)->generate([
        ...ownerEquityFilters(), 'report_view' => 'period', 'show_zero_balances' => false,
    ]);
    $balanceSheet = app(BalanceSheetReport::class)->generate([
        'as_of' => '2026-06-30', 'fiscal_year_start' => '2026-01-01',
        'fiscal_period_id' => null, 'show_zero_balances' => false,
    ]);

    expect($equity['summary']['net_income'])->toBe($income['summary']['net_income_after_tax'])
        ->and($equity['summary']['closing_equity'])->toBe($balanceSheet['summary']['total_equity'])
        ->and($equity['summary']['closing_equity'])->toBe('1375.0000');
});

it('keeps owner drawings separate from business expenses', function (): void {
    $context = ownerEquityContext();
    postOwnerEquityActivity($context);
    $statement = app(OwnerEquityStatementReport::class)->generate(ownerEquityFilters());

    expect($statement['summary']['net_income'])->toBe('200.0000')
        ->and($statement['summary']['drawings'])->toBe('-50.0000')
        ->and($statement['rows']->firstWhere('key', 'drawings')['label'])->toBe("Owner's Drawings");
});

it('provides reconciled drilldowns for equity activity and net income', function (): void {
    $context = ownerEquityContext();
    postOwnerEquityActivity($context);
    $report = app(OwnerEquityStatementReport::class);

    expect($report->drilldown(ownerEquityFilters(), 'contributions')['total'])->toBe('200.0000')
        ->and($report->drilldown(ownerEquityFilters(), 'drawings')['total'])->toBe('-50.0000')
        ->and($report->drilldown(ownerEquityFilters(), 'prior_period_adjustments')['total'])->toBe('25.0000')
        ->and($report->drilldown(ownerEquityFilters(), 'net_income')['total'])->toBe('200.0000')
        ->and($report->drilldown(ownerEquityFilters(), 'net_income')['rows'])->toHaveCount(2);

    $this->actingAs($context['user'])->get(route('owner-equity-statement.drilldown', [
        'activity' => 'prior_period_adjustments', ...ownerEquityFilters(),
    ]))->assertSuccessful()->assertSee('OE-PRIOR');
});

it('enforces permissions validation fiscal-period print and export', function (): void {
    $context = ownerEquityContext();
    expect(Permission::query()->whereIn('name', [
        'owner-equity-statement.view', 'owner-equity-statement.export', 'owner-equity-statement.drilldown',
    ])->count())->toBe(3);

    $none = User::factory()->create();
    $viewer = User::factory()->create();
    $viewer->givePermissionTo('owner-equity-statement.view');

    $this->actingAs($none)->get(route('owner-equity-statement.index'))->assertForbidden();
    $this->actingAs($viewer)->get(route('owner-equity-statement.index'))->assertSuccessful();
    $this->actingAs($viewer)->get(route('owner-equity-statement.export'))->assertForbidden();
    $this->actingAs($viewer)->get(route('owner-equity-statement.drilldown', 'contributions'))->assertForbidden();
    $this->actingAs($context['user'])->get(route('owner-equity-statement.index', [
        'start_date' => '2026-07-01', 'end_date' => '2026-06-30',
    ]))->assertSessionHasErrors('end_date');
    $this->actingAs($context['user'])->get(route('owner-equity-statement.index', [
        'fiscal_period_id' => $context['period']->id,
    ]))->assertSuccessful()->assertSee('2026-06-01')->assertSee('2026-06-30');
    $this->actingAs($context['user'])->get(route('owner-equity-statement.print', ownerEquityFilters()))
        ->assertSuccessful()->assertSeeText("Statement of Changes in Owner's Equity");
    $this->actingAs($context['user'])->get(route('owner-equity-statement.export', ownerEquityFilters()))
        ->assertSuccessful()->assertDownload();
    $this->actingAs($context['user'])->get(route('owner-equity-statement.drilldown', [
        'activity' => 'unsupported', ...ownerEquityFilters(),
    ]))->assertNotFound();

    expect(FinancialReportingConvention::PERMISSIONS)->toContain(
        'owner-equity-statement.view', 'owner-equity-statement.export', 'owner-equity-statement.drilldown',
    );
});
