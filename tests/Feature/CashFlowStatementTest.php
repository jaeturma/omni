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
use App\Reports\CashFlowStatementReport;
use App\Support\FinancialReportingConvention;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Spatie\Permission\Models\Permission;

uses(LazilyRefreshDatabase::class);

function cashFlowContext(): array
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
        '1010', '1100', '1200', '1400', '1500', '1590', '2010', '2100',
        '3010', '3020', '4010', '5010', '6150',
    ])->get()->keyBy('code');

    return compact('user', 'priorPeriod', 'period', 'accounts');
}

function saveCashFlowJournal(
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

function cashFlowFilters(array $changes = []): array
{
    return array_replace([
        'start_date' => '2026-06-01', 'end_date' => '2026-06-30',
        'as_of' => '2026-06-30', 'fiscal_period_id' => null,
    ], $changes);
}

function postCashFlowActivity(array $context): void
{
    $account = $context['accounts'];
    saveCashFlowJournal($context, 'CF-OPEN', '2025-12-31', [
        ['account_id' => $account['1010']->id, 'debit' => '1000.0000', 'credit' => '0.0000'],
        ['account_id' => $account['1100']->id, 'debit' => '200.0000', 'credit' => '0.0000'],
        ['account_id' => $account['1200']->id, 'debit' => '300.0000', 'credit' => '0.0000'],
        ['account_id' => $account['1500']->id, 'debit' => '500.0000', 'credit' => '0.0000'],
        ['account_id' => $account['2010']->id, 'debit' => '0.0000', 'credit' => '200.0000'],
        ['account_id' => $account['2100']->id, 'debit' => '0.0000', 'credit' => '300.0000'],
        ['account_id' => $account['3010']->id, 'debit' => '0.0000', 'credit' => '1500.0000'],
    ]);
    saveCashFlowJournal($context, 'CF-SALE', '2026-06-05', [
        ['account_id' => $account['1100']->id, 'debit' => '300.0000', 'credit' => '0.0000'],
        ['account_id' => $account['4010']->id, 'debit' => '0.0000', 'credit' => '300.0000'],
    ]);
    saveCashFlowJournal($context, 'CF-COLLECT', '2026-06-08', [
        ['account_id' => $account['1010']->id, 'debit' => '250.0000', 'credit' => '0.0000'],
        ['account_id' => $account['1100']->id, 'debit' => '0.0000', 'credit' => '250.0000'],
    ]);
    saveCashFlowJournal($context, 'CF-COST', '2026-06-10', [
        ['account_id' => $account['5010']->id, 'debit' => '100.0000', 'credit' => '0.0000'],
        ['account_id' => $account['1200']->id, 'debit' => '0.0000', 'credit' => '100.0000'],
    ]);
    saveCashFlowJournal($context, 'CF-PAY-AP', '2026-06-12', [
        ['account_id' => $account['2010']->id, 'debit' => '50.0000', 'credit' => '0.0000'],
        ['account_id' => $account['1010']->id, 'debit' => '0.0000', 'credit' => '50.0000'],
    ]);
    saveCashFlowJournal($context, 'CF-DEPR', '2026-06-15', [
        ['account_id' => $account['6150']->id, 'debit' => '40.0000', 'credit' => '0.0000'],
        ['account_id' => $account['1590']->id, 'debit' => '0.0000', 'credit' => '40.0000'],
    ]);
    saveCashFlowJournal($context, 'CF-EQUIP', '2026-06-18', [
        ['account_id' => $account['1500']->id, 'debit' => '200.0000', 'credit' => '0.0000'],
        ['account_id' => $account['1010']->id, 'debit' => '0.0000', 'credit' => '200.0000'],
    ]);
    saveCashFlowJournal($context, 'CF-CAPITAL', '2026-06-20', [
        ['account_id' => $account['1010']->id, 'debit' => '300.0000', 'credit' => '0.0000'],
        ['account_id' => $account['3010']->id, 'debit' => '0.0000', 'credit' => '300.0000'],
    ]);
    saveCashFlowJournal($context, 'CF-DRAW', '2026-06-22', [
        ['account_id' => $account['3020']->id, 'debit' => '80.0000', 'credit' => '0.0000'],
        ['account_id' => $account['1010']->id, 'debit' => '0.0000', 'credit' => '80.0000'],
    ]);
    saveCashFlowJournal($context, 'CF-LOAN', '2026-06-25', [
        ['account_id' => $account['2100']->id, 'debit' => '100.0000', 'credit' => '0.0000'],
        ['account_id' => $account['1010']->id, 'debit' => '0.0000', 'credit' => '100.0000'],
    ]);
}

it('reports operating investing financing working capital and reconciled cash', function (): void {
    $context = cashFlowContext();
    postCashFlowActivity($context);
    $statement = app(CashFlowStatementReport::class)->generate(cashFlowFilters());

    expect($statement['sections']['operating']['total'])->toBe('200.0000')
        ->and($statement['sections']['investing']['total'])->toBe('-200.0000')
        ->and($statement['sections']['financing']['total'])->toBe('120.0000')
        ->and($statement['summary'])->toMatchArray([
            'beginning_cash' => '1000.0000',
            'net_change' => '120.0000',
            'calculated_ending_cash' => '1120.0000',
            'ending_cash' => '1120.0000',
            'balance_sheet_cash' => '1120.0000',
            'reconciliation_difference' => '0.0000',
        ])
        ->and($statement['reconciled'])->toBeTrue()
        ->and($statement['has_unclassified'])->toBeFalse()
        ->and($statement['final_ready'])->toBeTrue();

    $operating = $statement['sections']['operating']['rows'];
    expect($operating->firstWhere('label', 'Net income')['amount'])->toBe('160.0000')
        ->and($operating->firstWhere('label', 'Change in accounts receivable')['amount'])->toBe('-50.0000')
        ->and($operating->firstWhere('label', 'Change in inventory')['amount'])->toBe('100.0000')
        ->and($operating->firstWhere('label', 'Change in accounts payable')['amount'])->toBe('-50.0000');
});

it('reconciles ending cash to the balance sheet and respects posted date-range activity', function (): void {
    $context = cashFlowContext();
    postCashFlowActivity($context);
    saveCashFlowJournal($context, 'CF-DRAFT', '2026-06-28', [
        ['account_id' => $context['accounts']['1010']->id, 'debit' => '999.0000', 'credit' => '0.0000'],
        ['account_id' => $context['accounts']['3010']->id, 'debit' => '0.0000', 'credit' => '999.0000'],
    ], null);

    $cashFlow = app(CashFlowStatementReport::class)->generate(cashFlowFilters());
    $balanceSheet = app(BalanceSheetReport::class)->generate([
        'as_of' => '2026-06-30', 'fiscal_year_start' => '2026-01-01',
        'fiscal_period_id' => null, 'show_zero_balances' => false,
    ]);
    $cashRow = $balanceSheet['sections']['current_assets']['rows']->firstWhere('account.code', '1010');

    expect($cashFlow['summary']['ending_cash'])->toBe($cashRow['amount'])
        ->and($cashFlow['summary']['ending_cash'])->toBe('1120.0000')
        ->and($cashFlow['reconciled'])->toBeTrue();
});

it('shows material unclassified activity without silently inferring a mapping', function (): void {
    $context = cashFlowContext();
    postCashFlowActivity($context);
    $context['accounts']['1400']->update(['cash_flow_classification' => null]);
    saveCashFlowJournal($context, 'CF-UNMAPPED', '2026-06-27', [
        ['account_id' => $context['accounts']['1400']->id, 'debit' => '60.0000', 'credit' => '0.0000'],
        ['account_id' => $context['accounts']['1010']->id, 'debit' => '0.0000', 'credit' => '60.0000'],
    ]);

    $statement = app(CashFlowStatementReport::class)->generate(cashFlowFilters());

    expect($statement['sections']['unclassified']['total'])->toBe('-60.0000')
        ->and($statement['has_unclassified'])->toBeTrue()
        ->and($statement['reconciled'])->toBeTrue()
        ->and($statement['final_ready'])->toBeFalse()
        ->and($context['accounts']['1400']->fresh()->cash_flow_classification)->toBeNull();
});

it('provides drilldowns that reconcile to classified activity', function (): void {
    $context = cashFlowContext();
    postCashFlowActivity($context);
    $inventory = $context['accounts']['1200'];
    $drilldown = app(CashFlowStatementReport::class)->drilldown(cashFlowFilters(), $inventory);

    expect($drilldown['total'])->toBe('100.0000')
        ->and($drilldown['rows'])->toHaveCount(1);

    $this->actingAs($context['user'])->get(route('cash-flow-statement.drilldown', [
        'account' => $inventory, ...cashFlowFilters(),
    ]))->assertSuccessful()->assertSee('CF-COST');
});

it('enforces permissions validation fiscal-period print export and mapping review', function (): void {
    $context = cashFlowContext();
    expect(Permission::query()->whereIn('name', [
        'cash-flow-statement.view', 'cash-flow-statement.export',
        'cash-flow-mapping.manage', 'cash-flow-statement.drilldown',
    ])->count())->toBe(4);

    $none = User::factory()->create();
    $viewer = User::factory()->create();
    $viewer->givePermissionTo('cash-flow-statement.view');

    $this->actingAs($none)->get(route('cash-flow-statement.index'))->assertForbidden();
    $this->actingAs($viewer)->get(route('cash-flow-statement.index'))->assertSuccessful();
    $this->actingAs($viewer)->get(route('cash-flow-statement.export'))->assertForbidden();
    $this->actingAs($viewer)->get(route('cash-flow-statement.mappings'))->assertForbidden();
    $this->actingAs($viewer)->get(route('cash-flow-statement.drilldown', $context['accounts']['1200']))->assertForbidden();
    $this->actingAs($context['user'])->get(route('cash-flow-statement.index', [
        'start_date' => '2026-07-01', 'end_date' => '2026-06-30',
    ]))->assertSessionHasErrors('end_date');
    $this->actingAs($context['user'])->get(route('cash-flow-statement.index', [
        'fiscal_period_id' => $context['period']->id,
    ]))->assertSuccessful()->assertSee('2026-06-01')->assertSee('2026-06-30');
    $this->actingAs($context['user'])->get(route('cash-flow-statement.print', cashFlowFilters()))
        ->assertSuccessful()->assertSee('Cash Flow Statement');
    $this->actingAs($context['user'])->get(route('cash-flow-statement.export', cashFlowFilters()))
        ->assertSuccessful()->assertDownload();
    $this->actingAs($context['user'])->get(route('cash-flow-statement.mappings', cashFlowFilters()))
        ->assertSuccessful()->assertSee('Cash Flow Mapping Review');

    expect(FinancialReportingConvention::PERMISSIONS)->toContain(
        'cash-flow-statement.view', 'cash-flow-statement.export',
        'cash-flow-mapping.manage', 'cash-flow-statement.drilldown',
    );
});
