<?php

use App\Enums\AccountClass;
use App\Enums\AccountType;
use App\Enums\NormalBalance;
use App\Models\Account;
use App\Models\BusinessProfile;
use App\Models\FiscalPeriod;
use App\Models\JournalEntry;
use App\Models\TaxPeriod;
use App\Models\TaxProfile;
use App\Models\User;
use App\Services\BooksAndSchedules;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

uses(LazilyRefreshDatabase::class);
beforeEach(fn () => $this->seed(RolesAndPermissionsSeeder::class));

function booksFixture(?string $bookType = 'manual'): array
{
    $user = User::factory()->administrator()->create();
    $business = BusinessProfile::factory()->active()->create(['registered_business_name' => 'Omni Books Test']);
    $profile = $bookType === null ? null : TaxProfile::query()->create(['business_profile_id' => $business->id, 'taxpayer_type' => 'sole_proprietorship', 'registration_type' => 'registered', 'vat_status' => 'non_vat', 'income_tax_option' => 'graduated', 'percentage_tax_registered' => true, 'filing_frequency' => 'quarterly', 'registration_start_date' => '2026-01-01', 'first_filing_period' => '2026-Q1', 'rdo_code' => '050', 'tin' => '123-456-789', 'branch_code' => '00000', 'registered_books_type' => $bookType, 'active' => true]);
    $period = FiscalPeriod::factory()->create(['starts_on' => '2026-01-01', 'ends_on' => '2026-12-31', 'status' => 'open']);
    $cash = Account::query()->create(['code' => '1000', 'name' => 'Cash', 'account_class' => AccountClass::Asset, 'account_type' => AccountType::Cash, 'normal_balance' => NormalBalance::Debit]);
    $income = Account::query()->create(['code' => '4000', 'name' => 'Sales', 'account_class' => AccountClass::Income, 'account_type' => AccountType::SalesIncome, 'normal_balance' => NormalBalance::Credit]);

    return compact('user', 'business', 'profile', 'period', 'cash', 'income');
}

function booksJournal(array $fixture, string $number, string $date, string $status, string $amount): JournalEntry
{
    $entry = JournalEntry::query()->create(['journal_number' => $number, 'journal_date' => $date, 'document_date' => $date, 'fiscal_period_id' => $fixture['period']->id, 'journal_type' => 'cash_receipt', 'source_type' => 'cash_receipt', 'description' => 'Collection', 'total_debit' => $amount, 'total_credit' => $amount, 'status' => $status, 'posted_at' => $status === 'posted' ? now() : null, 'posted_by' => $status === 'posted' ? $fixture['user']->id : null, 'created_by' => $fixture['user']->id, 'updated_by' => $fixture['user']->id]);
    $entry->lines()->create(['account_id' => $fixture['cash']->id, 'line_number' => 1, 'debit' => $amount, 'credit' => '0.0000']);
    $entry->lines()->create(['account_id' => $fixture['income']->id, 'line_number' => 2, 'debit' => '0.0000', 'credit' => $amount]);

    return $entry;
}

it('includes posted records and excludes voided records deterministically', function (): void {
    $fixture = booksFixture();
    booksJournal($fixture, 'CR-002', '2026-05-02', 'posted', '200.0000');
    booksJournal($fixture, 'CR-001', '2026-05-01', 'posted', '100.0000');
    booksJournal($fixture, 'CR-VOID', '2026-05-03', 'voided', '900.0000');

    $data = app(BooksAndSchedules::class)->generate(['report' => 'general_journal', 'start_date' => '2026-05-01', 'end_date' => '2026-05-31'], $fixture['user']);
    expect($data['rows'])->toHaveCount(2)->and($data['rows']->pluck('Reference')->all())->toBe(['CR-001', 'CR-002'])
        ->and($data['totals']['Debit'])->toBe('300.0000')->and($data['totals']['Credit'])->toBe('300.0000');
});

it('reports beginning and ending cash balances', function (): void {
    $fixture = booksFixture();
    booksJournal($fixture, 'CR-OPEN', '2026-04-30', 'posted', '50.0000');
    booksJournal($fixture, 'CR-MAY', '2026-05-10', 'posted', '125.0000');

    $data = app(BooksAndSchedules::class)->generate(['report' => 'cash_receipts', 'start_date' => '2026-05-01', 'end_date' => '2026-05-31'], $fixture['user']);
    expect($data['balances'])->toBe(['beginning' => '50.0000', 'ending' => '175.0000'])->and($data['rows'])->toHaveCount(1);
});

it('resolves tax-period parameters and discloses registered-book status', function (): void {
    $fixture = booksFixture('loose_leaf');
    $taxPeriod = TaxPeriod::query()->create(['tax_profile_id' => $fixture['profile']->id, 'frequency' => 'quarterly', 'period_start' => '2026-04-01', 'period_end' => '2026-06-30', 'capture_start' => '2026-05-01', 'tax_year' => 2026, 'quarter' => 2, 'label' => 'Q2 2026']);
    $data = app(BooksAndSchedules::class)->generate(['report' => 'general_journal', 'start_date' => '2020-01-01', 'end_date' => '2020-12-31', 'tax_period_id' => $taxPeriod->id], $fixture['user']);

    expect($data['filters']['start_date'])->toBe('2026-05-01')->and($data['filters']['end_date'])->toBe('2026-06-30')
        ->and($data['classification'])->toBe('Loose-leaf draft')->and($data['configuration_warning'])->toBeNull()
        ->and($data['disclaimer'])->toContain('not automatically an approved or registered BIR book');
});

it('shows a warning when registered-book configuration is missing', function (): void {
    $fixture = booksFixture(null);
    $response = $this->actingAs($fixture['user'])->get(route('books-of-accounts.index', ['report' => 'general_journal', 'start_date' => '2026-01-01', 'end_date' => '2026-12-31']));
    $response->assertSuccessful()->assertSee('Registered-book configuration is missing')->assertSee('not automatically an approved or registered BIR book');
});

it('exports every required book and schedule and reconciles CSV totals', function (): void {
    $fixture = booksFixture();
    booksJournal($fixture, 'CR-CSV', '2026-05-01', 'posted', '123.4500');
    foreach (BooksAndSchedules::REPORTS as $report => $definition) {
        $route = $definition['group'] === 'books' ? 'books-of-accounts.export' : 'tax-schedules.export';
        $response = $this->actingAs($fixture['user'])->get(route($route, ['report' => $report, 'start_date' => '2026-01-01', 'end_date' => '2026-12-31']));
        $response->assertSuccessful()->assertHeader('content-type', 'text/csv; charset=UTF-8');
        if ($report === 'general_journal') {
            expect($response->streamedContent())->toContain('123.4500')->toContain('Total Debit');
        }
    }
});

it('enforces separate book and schedule permissions', function (): void {
    $fixture = booksFixture();
    $unauthorized = User::factory()->create();
    $this->actingAs($unauthorized)->get(route('books-of-accounts.index'))->assertForbidden();
    $this->actingAs($unauthorized)->get(route('tax-schedules.index'))->assertForbidden();
    expect($fixture['user']->can('books-of-accounts.export'))->toBeTrue()->and($fixture['user']->can('tax-schedules.export'))->toBeTrue();
});
