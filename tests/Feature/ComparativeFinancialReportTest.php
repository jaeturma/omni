<?php

use App\Actions\SaveJournalEntry;
use App\Actions\TransitionJournalEntry;
use App\Enums\JournalEntryStatus;
use App\Models\Account;
use App\Models\FiscalPeriod;
use App\Models\FiscalYear;
use App\Models\User;
use App\Reports\ComparativeFinancialReport;
use App\Support\FinancialReportingConvention;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Spatie\Permission\Models\Permission;

uses(LazilyRefreshDatabase::class);

function comparativeContext(): array
{
    test()->seed([RolesAndPermissionsSeeder::class, ChartOfAccountsSeeder::class]);
    $user = User::factory()->create();
    $user->assignRole('Bookkeeper');
    $year = FiscalYear::factory()->create(['name' => 'FY 2026', 'starts_on' => '2026-01-01', 'ends_on' => '2026-12-31', 'is_current' => true]);
    $priorYear = FiscalYear::factory()->create(['name' => 'FY 2025', 'starts_on' => '2025-01-01', 'ends_on' => '2025-12-31', 'is_current' => false]);
    $periods = collect([
        ['year' => $priorYear, 'name' => 'June 2025', 'start' => '2025-06-01', 'end' => '2025-06-30', 'month' => 6, 'quarter' => 2],
        ['year' => $year, 'name' => 'March 2026', 'start' => '2026-03-01', 'end' => '2026-03-31', 'month' => 3, 'quarter' => 1],
        ['year' => $year, 'name' => 'April 2026', 'start' => '2026-04-01', 'end' => '2026-04-30', 'month' => 4, 'quarter' => 2],
        ['year' => $year, 'name' => 'May 2026', 'start' => '2026-05-01', 'end' => '2026-05-31', 'month' => 5, 'quarter' => 2],
        ['year' => $year, 'name' => 'June 2026', 'start' => '2026-06-01', 'end' => '2026-06-30', 'month' => 6, 'quarter' => 2],
    ])->mapWithKeys(function (array $data): array {
        $period = FiscalPeriod::factory()->create([
            'fiscal_year_id' => $data['year']->id, 'name' => $data['name'], 'starts_on' => $data['start'], 'ends_on' => $data['end'],
            'calendar_year' => (int) mb_substr($data['start'], 0, 4), 'calendar_month' => $data['month'], 'calendar_quarter' => $data['quarter'], 'status' => 'open',
        ]);

        return [$data['start'] => $period];
    });
    $accounts = Account::query()->whereIn('code', ['1010', '3010', '4010', '6010'])->get()->keyBy('code');

    return compact('user', 'periods', 'accounts');
}

function postComparativeEntry(array $context, string $number, string $date, string $revenue, string $expense = '0.0000'): void
{
    $period = $context['periods']->first(fn (FiscalPeriod $period): bool => $period->starts_on->lte($date) && $period->ends_on->gte($date));
    $netCash = bcsub($revenue, $expense, 4);
    $lines = [
        ['account_id' => $context['accounts']['1010']->id, 'debit' => $netCash, 'credit' => '0.0000'],
        ['account_id' => $context['accounts']['4010']->id, 'debit' => '0.0000', 'credit' => $revenue],
    ];
    if (bccomp($expense, '0', 4) !== 0) {
        $lines[] = ['account_id' => $context['accounts']['6010']->id, 'debit' => $expense, 'credit' => '0.0000'];
    }
    $entry = app(SaveJournalEntry::class)->handle([
        'journal_number' => $number, 'journal_date' => $date, 'document_date' => $date, 'fiscal_period_id' => $period->id,
        'journal_type' => 'adjustment', 'source_type' => 'manual', 'reference_number' => $number, 'description' => $number, 'lines' => $lines,
    ], $context['user']->id);
    app(TransitionJournalEntry::class)->handle($entry, JournalEntryStatus::Posted, $context['user']->id);
}

function comparativeFilters(array $changes = []): array
{
    return array_replace([
        'report_type' => 'income_statement', 'comparison_type' => 'prior_month', 'reference_date' => '2026-06-30',
        'current_start_date' => '2026-06-01', 'current_end_date' => '2026-06-30',
        'comparison_start_date' => '2026-05-01', 'comparison_end_date' => '2026-05-31',
    ], $changes);
}

it('compares months with absolute percentage zero and negative variances safely', function (): void {
    $context = comparativeContext();
    postComparativeEntry($context, 'CMP-MAY', '2026-05-15', '100.0000', '200.0000');
    postComparativeEntry($context, 'CMP-JUN', '2026-06-15', '150.0000', '100.0000');
    $report = app(ComparativeFinancialReport::class)->generate(comparativeFilters());
    $revenue = $report['sections']->firstWhere('key', 'revenue')['rows']->firstWhere('account.code', '4010');
    $expense = $report['sections']->firstWhere('key', 'operating_expenses')['rows']->firstWhere('account.code', '6010');
    $zero = $report['sections']->firstWhere('key', 'other_income');

    expect($revenue)->toMatchArray(['current_amount' => '150.0000', 'comparison_amount' => '100.0000', 'absolute_variance' => '50.0000', 'percentage_variance' => '50.00'])
        ->and($expense['absolute_variance'])->toBe('-100.0000')
        ->and($expense['percentage_variance'])->toBe('-50.00')
        ->and($zero['percentage_variance'])->toBeNull();
});

it('derives quarter prior-year and year-to-date periods reproducibly', function (string $type, array $expected): void {
    $context = comparativeContext();
    $response = $this->actingAs($context['user'])->get(route('comparative-reports.index', [
        'comparison_type' => $type, 'reference_date' => '2026-06-30',
    ]));
    $response->assertSuccessful();
    foreach ($expected as $date) {
        $response->assertSee($date);
    }
})->with([
    'quarter' => ['prior_quarter', ['2026-04-01', '2026-06-30', '2026-01-01', '2026-03-31']],
    'prior year' => ['prior_year', ['2026-06-01', '2026-06-30', '2025-06-01', '2025-06-30']],
    'prior YTD' => ['prior_ytd', ['2026-01-01', '2026-06-30', '2025-01-01', '2025-06-30']],
]);

it('uses identical account mappings for income statement and balance sheet trends', function (): void {
    $context = comparativeContext();
    postComparativeEntry($context, 'CMP-MAY-MAP', '2026-05-15', '100.0000');
    postComparativeEntry($context, 'CMP-JUN-MAP', '2026-06-15', '150.0000');
    $income = app(ComparativeFinancialReport::class)->generate(comparativeFilters());
    $balance = app(ComparativeFinancialReport::class)->generate(comparativeFilters(['report_type' => 'balance_sheet']));

    expect($income['mapping_rule'])->toBe(FinancialReportingConvention::COMPARATIVE_MAPPING_RULE)
        ->and($income['sections']->firstWhere('key', 'revenue')['rows'])->each(fn ($row) => $row->toHaveKeys(['current_amount', 'comparison_amount']))
        ->and($balance['sections']->firstWhere('key', 'current_assets')['rows'])->each(fn ($row) => $row->toHaveKeys(['current_amount', 'comparison_amount']));
});

it('enforces authorization validation print export and seeded permissions', function (): void {
    $context = comparativeContext();
    expect(Permission::query()->whereIn('name', ['comparative-reports.view', 'comparative-reports.export'])->count())->toBe(2);
    $none = User::factory()->create();
    $viewer = User::factory()->create();
    $viewer->givePermissionTo('comparative-reports.view');

    $this->actingAs($none)->get(route('comparative-reports.index'))->assertForbidden();
    $this->actingAs($viewer)->get(route('comparative-reports.index'))->assertSuccessful();
    $this->actingAs($viewer)->get(route('comparative-reports.export'))->assertForbidden();
    $this->actingAs($context['user'])->get(route('comparative-reports.index', comparativeFilters([
        'comparison_type' => 'custom', 'comparison_end_date' => '2026-06-15',
    ])))->assertSessionHasErrors('comparison_end_date');
    $this->actingAs($context['user'])->get(route('comparative-reports.print', comparativeFilters()))->assertSuccessful()->assertSee('Comparative Income Statement');
    $this->actingAs($context['user'])->get(route('comparative-reports.export', comparativeFilters()))->assertSuccessful()->assertDownload();
});
