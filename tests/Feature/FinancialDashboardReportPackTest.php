<?php

use App\Actions\SaveJournalEntry;
use App\Actions\TransitionJournalEntry;
use App\Enums\JournalEntryStatus;
use App\Models\Account;
use App\Models\FiscalPeriod;
use App\Models\FiscalYear;
use App\Models\SourcePosting;
use App\Models\User;
use App\Reports\BalanceSheetReport;
use App\Reports\IncomeStatementReport;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

uses(LazilyRefreshDatabase::class);

function financialDashboardContext(): array
{
    test()->seed([RolesAndPermissionsSeeder::class, ChartOfAccountsSeeder::class]);
    $user = User::factory()->create();
    $user->assignRole('Bookkeeper');
    $year = FiscalYear::factory()->create(['name' => 'FY 2026', 'starts_on' => '2026-01-01', 'ends_on' => '2026-12-31']);
    $period = FiscalPeriod::factory()->create(['fiscal_year_id' => $year->id, 'name' => 'July 2026',
        'starts_on' => '2026-07-01', 'ends_on' => '2026-07-31', 'calendar_year' => 2026,
        'calendar_month' => 7, 'calendar_quarter' => 3, 'status' => 'open']);
    $accounts = Account::query()->whereIn('code', ['1010', '4010', '5010', '6010'])->get()->keyBy('code');
    $entry = app(SaveJournalEntry::class)->handle([
        'journal_number' => 'DASH-001', 'journal_date' => '2026-07-15', 'document_date' => '2026-07-15',
        'fiscal_period_id' => $period->id, 'journal_type' => 'sales', 'source_type' => 'manual', 'description' => 'Dashboard sale',
        'lines' => [
            ['account_id' => $accounts['1010']->id, 'debit' => '600.0000', 'credit' => '0.0000'],
            ['account_id' => $accounts['5010']->id, 'debit' => '250.0000', 'credit' => '0.0000'],
            ['account_id' => $accounts['6010']->id, 'debit' => '50.0000', 'credit' => '0.0000'],
            ['account_id' => $accounts['4010']->id, 'debit' => '0.0000', 'credit' => '900.0000'],
        ],
    ], $user->id);
    app(TransitionJournalEntry::class)->handle($entry, JournalEntryStatus::Posted, $user->id);

    return compact('user', 'period');
}

it('reconciles dashboard period metrics and exposes explicit filters', function (): void {
    $context = financialDashboardContext();
    $response = $this->actingAs($context['user'])->get(route('financial-dashboard', ['fiscal_period_id' => $context['period']->id]));
    $statement = app(IncomeStatementReport::class)->generate(['start_date' => '2026-07-01', 'end_date' => '2026-07-31',
        'as_of' => '2026-07-31', 'fiscal_period_id' => $context['period']->id, 'report_view' => 'period', 'show_zero_balances' => false]);
    $balanceSheet = app(BalanceSheetReport::class)->generate(['as_of' => '2026-07-31', 'fiscal_year_start' => '2026-01-01',
        'fiscal_period_id' => $context['period']->id, 'show_zero_balances' => false]);
    $cash = $balanceSheet['sections']['current_assets']['rows']->firstWhere('account.code', '1010')['amount'];

    $response->assertSuccessful()->assertSee('2026-07-01 to 2026-07-31')->assertSee('Last refreshed')
        ->assertViewHas('metrics', fn (array $metrics): bool => $metrics['cash'] === $cash
            && $metrics['gross_profit'] === $statement['summary']['gross_profit']
            && $metrics['operating_expenses'] === $statement['summary']['operating_expenses']
            && $metrics['net_income'] === $statement['summary']['net_income_after_tax']);
});

it('shows critical posting warnings and suppresses potentially misleading values', function (): void {
    $context = financialDashboardContext();
    SourcePosting::query()->create(['source_type' => 'sales_invoice', 'source_id' => 999, 'status' => 'failed',
        'attempts' => 1, 'last_error' => 'Test posting failure']);

    $this->actingAs($context['user'])->get(route('financial-dashboard', ['fiscal_period_id' => $context['period']->id]))
        ->assertSuccessful()->assertSee('Financial values temporarily unavailable')->assertSee('Failed source postings')->assertSee('Unavailable');
});

it('enforces dashboard generation and download permissions', function (): void {
    $context = financialDashboardContext();
    $restricted = User::factory()->create();

    $this->actingAs($restricted)->get(route('financial-dashboard', ['fiscal_period_id' => $context['period']->id]))->assertForbidden();
    $restricted->givePermissionTo('financial-report-pack.generate');
    $this->actingAs($restricted)->get(route('financial-report-pack.download', ['fiscal_period_id' => $context['period']->id]))->assertForbidden();
});

it('generates and downloads all required report-pack summaries with consistent totals', function (): void {
    $context = financialDashboardContext();
    $parameters = ['fiscal_period_id' => $context['period']->id];

    $this->actingAs($context['user'])->get(route('financial-report-pack.show', $parameters))
        ->assertSuccessful()->assertSee('Income Statement')->assertSee('Balance Sheet')->assertSee('Cash-flow Statement')
        ->assertSee("Owner's Equity Statement")->assertSee('Trial Balance Summary')->assertSee('AR Aging Summary')
        ->assertSee('AP Aging Summary')->assertSee('Cash-position Summary')->assertSee('Inventory-valuation Summary');

    $csv = $this->actingAs($context['user'])->get(route('financial-report-pack.download', $parameters))
        ->assertSuccessful()->streamedContent();
    expect($csv)->toContain('Income Statement')->toContain('"Net Income After Tax",600.0000')
        ->toContain('Trial Balance Summary')->toContain('AR Aging Summary')->toContain('Inventory-valuation Summary');
});

it('keeps dashboard query volume bounded as rows grow', function (): void {
    $context = financialDashboardContext();
    $queries = 0;
    DB::listen(function () use (&$queries): void {
        $queries++;
    });

    $this->actingAs($context['user'])->get(route('financial-dashboard', ['fiscal_period_id' => $context['period']->id]))->assertSuccessful();

    expect($queries)->toBeLessThan(100);
});
