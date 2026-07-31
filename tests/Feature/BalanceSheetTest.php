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
use App\Support\FinancialReportingConvention;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Spatie\Permission\Models\Permission;

uses(LazilyRefreshDatabase::class);

function balanceSheetContext(): array
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
    $july = FiscalPeriod::factory()->create([
        'fiscal_year_id' => $year->id, 'name' => 'July 2026',
        'starts_on' => '2026-07-01', 'ends_on' => '2026-07-31',
        'calendar_year' => 2026, 'calendar_month' => 7, 'calendar_quarter' => 3, 'status' => 'open',
    ]);
    $accounts = Account::query()->whereIn('code', [
        '1010', '1100', '1200', '1500', '1590', '2010', '2030', '2100',
        '3010', '3020', '3030', '4010', '6010',
    ])->get()->keyBy('code');

    return compact('user', 'priorPeriod', 'year', 'july', 'accounts');
}

function saveBalanceJournal(
    array $context,
    string $number,
    string $date,
    array $lines,
    string $type = 'adjustment',
    ?JournalEntryStatus $status = JournalEntryStatus::Posted,
): JournalEntry {
    $entry = app(SaveJournalEntry::class)->handle([
        'journal_number' => $number, 'journal_date' => $date, 'document_date' => $date,
        'fiscal_period_id' => $date < '2026-01-01' ? $context['priorPeriod']->id : $context['july']->id,
        'journal_type' => $type, 'source_type' => 'manual', 'reference_number' => $number,
        'description' => $number, 'lines' => $lines,
    ], $context['user']->id);

    return $status === null ? $entry : app(TransitionJournalEntry::class)->handle(
        $entry,
        $status,
        $context['user']->id,
        $status === JournalEntryStatus::Voided ? 'Excluded test entry' : null,
    );
}

function balanceSheetFilters(array $changes = []): array
{
    return array_replace([
        'as_of' => '2026-07-31', 'fiscal_year_start' => '2026-01-01',
        'fiscal_period_id' => null, 'show_zero_balances' => false,
    ], $changes);
}

function postBalanceSheetActivity(array $context): void
{
    $accounts = $context['accounts'];
    saveBalanceJournal($context, 'BS-OPEN', '2026-07-01', [
        ['account_id' => $accounts['1010']->id, 'debit' => '500.0000', 'credit' => '0.0000'],
        ['account_id' => $accounts['1100']->id, 'debit' => '300.0000', 'credit' => '0.0000'],
        ['account_id' => $accounts['1200']->id, 'debit' => '200.0000', 'credit' => '0.0000'],
        ['account_id' => $accounts['1500']->id, 'debit' => '400.0000', 'credit' => '0.0000'],
        ['account_id' => $accounts['1590']->id, 'debit' => '0.0000', 'credit' => '100.0000'],
        ['account_id' => $accounts['2010']->id, 'debit' => '0.0000', 'credit' => '250.0000'],
        ['account_id' => $accounts['2030']->id, 'debit' => '0.0000', 'credit' => '50.0000'],
        ['account_id' => $accounts['2100']->id, 'debit' => '0.0000', 'credit' => '300.0000'],
        ['account_id' => $accounts['3010']->id, 'debit' => '0.0000', 'credit' => '700.0000'],
    ], 'opening');
    saveBalanceJournal($context, 'BS-INCOME', '2026-07-15', [
        ['account_id' => $accounts['1010']->id, 'debit' => '120.0000', 'credit' => '0.0000'],
        ['account_id' => $accounts['6010']->id, 'debit' => '80.0000', 'credit' => '0.0000'],
        ['account_id' => $accounts['4010']->id, 'debit' => '0.0000', 'credit' => '200.0000'],
    ]);
    saveBalanceJournal($context, 'BS-DRAW', '2026-07-20', [
        ['account_id' => $accounts['3020']->id, 'debit' => '50.0000', 'credit' => '0.0000'],
        ['account_id' => $accounts['1010']->id, 'debit' => '0.0000', 'credit' => '50.0000'],
    ]);
}

it('aggregates classified assets liabilities equity current earnings and contra assets', function (): void {
    $context = balanceSheetContext();
    postBalanceSheetActivity($context);

    $statement = app(BalanceSheetReport::class)->generate(balanceSheetFilters());
    $nonCurrentAssets = $statement['sections']['non_current_assets']['rows'];

    expect($statement['summary'])->toMatchArray([
        'current_assets' => '1070.0000',
        'non_current_assets' => '300.0000',
        'total_assets' => '1370.0000',
        'current_liabilities' => '300.0000',
        'non_current_liabilities' => '300.0000',
        'total_liabilities' => '600.0000',
        'owner_capital' => '700.0000',
        'owner_drawings' => '-50.0000',
        'current_year_earnings' => '120.0000',
        'total_equity' => '770.0000',
        'liabilities_and_equity' => '1370.0000',
        'difference' => '0.0000',
    ])->and($nonCurrentAssets->firstWhere('account.code', '1590')['amount'])->toBe('-100.0000')
        ->and($statement['current_year_earnings_derived'])->toBeTrue()
        ->and($statement['balanced'])->toBeTrue()
        ->and($statement['final_ready'])->toBeTrue();
});

it('respects as-of dates and uses posted closing entries when formally closed', function (): void {
    $context = balanceSheetContext();
    postBalanceSheetActivity($context);
    saveBalanceJournal($context, 'BS-DRAFT', '2026-07-10', [
        ['account_id' => $context['accounts']['1010']->id, 'debit' => '999.0000', 'credit' => '0.0000'],
        ['account_id' => $context['accounts']['3010']->id, 'debit' => '0.0000', 'credit' => '999.0000'],
    ], status: null);

    $early = app(BalanceSheetReport::class)->generate(balanceSheetFilters(['as_of' => '2026-07-10']));
    expect($early['summary']['total_assets'])->toBe('1300.0000')
        ->and($early['summary']['current_year_earnings'])->toBe('0.0000')
        ->and($early['balanced'])->toBeTrue();

    saveBalanceJournal($context, 'BS-CLOSE', '2026-07-31', [
        ['account_id' => $context['accounts']['4010']->id, 'debit' => '200.0000', 'credit' => '0.0000'],
        ['account_id' => $context['accounts']['6010']->id, 'debit' => '0.0000', 'credit' => '80.0000'],
        ['account_id' => $context['accounts']['3030']->id, 'debit' => '0.0000', 'credit' => '120.0000'],
    ], 'closing');
    $closed = app(BalanceSheetReport::class)->generate(balanceSheetFilters());

    expect($closed['summary']['current_year_earnings'])->toBe('120.0000')
        ->and($closed['current_year_earnings_derived'])->toBeFalse()
        ->and($closed['balanced'])->toBeTrue();

    $this->actingAs($context['user'])->get(route('balance-sheet.index', [
        'fiscal_period_id' => $context['july']->id,
    ]))->assertSuccessful()->assertSee('2026-07-31');
});

it('shows an imbalance and blocks final-ready status without creating an adjustment', function (): void {
    $context = balanceSheetContext();
    saveBalanceJournal($context, 'BS-PRIOR-GAP', '2025-12-31', [
        ['account_id' => $context['accounts']['1010']->id, 'debit' => '100.0000', 'credit' => '0.0000'],
        ['account_id' => $context['accounts']['4010']->id, 'debit' => '0.0000', 'credit' => '100.0000'],
    ]);

    $statement = app(BalanceSheetReport::class)->generate(balanceSheetFilters());

    expect($statement['summary']['difference'])->toBe('100.0000')
        ->and($statement['balanced'])->toBeFalse()
        ->and($statement['final_ready'])->toBeFalse()
        ->and(JournalEntry::query()->count())->toBe(1);
});

it('provides reconciled account and derived-earnings drilldowns', function (): void {
    $context = balanceSheetContext();
    postBalanceSheetActivity($context);
    $report = app(BalanceSheetReport::class);
    $assetHeader = Account::query()->where('code', '1000')->sole();
    $earnings = $context['accounts']['3030'];
    $assets = $report->drilldown(balanceSheetFilters(), $assetHeader);
    $derived = $report->drilldown(balanceSheetFilters(), $earnings);

    expect($assets['total'])->toBe('1070.0000')
        ->and($assets['derived'])->toBeFalse()
        ->and($derived['total'])->toBe('120.0000')
        ->and($derived['derived'])->toBeTrue()
        ->and($derived['rows'])->toHaveCount(2);

    $this->actingAs($context['user'])->get(route('balance-sheet.drilldown', [
        'account' => $earnings, ...balanceSheetFilters(),
    ]))->assertSuccessful()->assertSee('BS-INCOME');
});

it('seeds and enforces balance-sheet permissions validation print and export', function (): void {
    $context = balanceSheetContext();
    expect(Permission::query()->whereIn('name', [
        'balance-sheet.view', 'balance-sheet.export', 'balance-sheet.drilldown',
    ])->count())->toBe(3);

    $none = User::factory()->create();
    $viewer = User::factory()->create();
    $viewer->givePermissionTo('balance-sheet.view');
    $cash = $context['accounts']['1010'];

    $this->actingAs($none)->get(route('balance-sheet.index'))->assertForbidden();
    $this->actingAs($viewer)->get(route('balance-sheet.index'))->assertSuccessful();
    $this->actingAs($viewer)->get(route('balance-sheet.export'))->assertForbidden();
    $this->actingAs($viewer)->get(route('balance-sheet.drilldown', $cash))->assertForbidden();
    $this->actingAs($context['user'])->get(route('balance-sheet.index', [
        'as_of' => '2025-12-31', 'fiscal_year_start' => '2026-01-01',
    ]))->assertSessionHasErrors('fiscal_year_start');
    $this->actingAs($context['user'])->get(route('balance-sheet.print', balanceSheetFilters()))
        ->assertSuccessful()->assertSee('Balance Sheet');
    $this->actingAs($context['user'])->get(route('balance-sheet.export', balanceSheetFilters()))
        ->assertSuccessful()->assertDownload('balance-sheet-2026-07-31.csv');

    expect(FinancialReportingConvention::PERMISSIONS)->toContain(
        'balance-sheet.view', 'balance-sheet.export', 'balance-sheet.drilldown',
    );
});
