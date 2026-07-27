<?php

use App\Actions\ReverseJournalEntry;
use App\Actions\SaveJournalEntry;
use App\Actions\TransitionJournalEntry;
use App\Enums\JournalEntryStatus;
use App\Models\Account;
use App\Models\FiscalPeriod;
use App\Models\FiscalYear;
use App\Models\JournalEntry;
use App\Models\User;
use App\Reports\GeneralLedgerReport;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

uses(LazilyRefreshDatabase::class);

function ledgerReportContext(): array
{
    test()->seed([RolesAndPermissionsSeeder::class, ChartOfAccountsSeeder::class]);
    $user = User::factory()->create();
    $user->assignRole('Bookkeeper');
    $year = FiscalYear::factory()->create(['starts_on' => '2026-01-01', 'ends_on' => '2026-12-31']);
    $period = FiscalPeriod::factory()->create([
        'fiscal_year_id' => $year->id,
        'starts_on' => '2026-01-01',
        'ends_on' => '2026-12-31',
        'status' => 'open',
    ]);
    $cash = Account::query()->where('code', '1010')->sole();
    $capital = Account::query()->where('code', '3010')->sole();

    return compact('user', 'period', 'cash', 'capital');
}

function postLedgerJournal(array $context, string $number, string $date, string $debit, string $reference): JournalEntry
{
    $entry = app(SaveJournalEntry::class)->handle([
        'journal_number' => $number,
        'journal_date' => $date,
        'document_date' => $date,
        'fiscal_period_id' => $context['period']->id,
        'journal_type' => 'adjustment',
        'source_type' => 'manual',
        'source_id' => null,
        'reference_number' => $reference,
        'description' => "Journal {$number}",
        'lines' => [
            ['account_id' => $context['cash']->id, 'description' => 'Cash movement', 'debit' => $debit, 'credit' => '0.0000'],
            ['account_id' => $context['capital']->id, 'description' => 'Capital movement', 'debit' => '0.0000', 'credit' => $debit],
        ],
    ], $context['user']->id);

    return app(TransitionJournalEntry::class)->handle($entry, JournalEntryStatus::Posted, $context['user']->id);
}

it('calculates opening movement closing and normal running balances from posted lines', function (): void {
    $context = ledgerReportContext();
    postLedgerJournal($context, 'JE-OPEN', '2026-06-30', '100.1234', 'OPEN');
    postLedgerJournal($context, 'JE-JUL-1', '2026-07-05', '25.1111', 'KEEP');
    postLedgerJournal($context, 'JE-JUL-2', '2026-07-10', '4.0000', 'KEEP');

    $cash = app(GeneralLedgerReport::class)->ledger([
        'start_date' => '2026-07-01', 'end_date' => '2026-07-31',
        'account_id' => $context['cash']->id, 'include_descendants' => false,
    ], false);
    $capital = app(GeneralLedgerReport::class)->ledger([
        'start_date' => '2026-07-01', 'end_date' => '2026-07-31',
        'account_id' => $context['capital']->id, 'include_descendants' => false,
    ], false);

    expect($cash['opening'])->toBe('100.1234')
        ->and($cash['debit'])->toBe('29.1111')
        ->and($cash['credit'])->toBe('0.0000')
        ->and($cash['closing'])->toBe('129.2345')
        ->and($cash['rows']->pluck('running_balance')->all())->toBe(['125.2345', '129.2345'])
        ->and($capital['opening'])->toBe('100.1234')
        ->and($capital['closing'])->toBe('129.2345')
        ->and($capital['rows']->last()->running_balance)->toBe('129.2345');
});

it('shows reversals separately excludes voided entries and reconciles journal totals', function (): void {
    $context = ledgerReportContext();
    $original = postLedgerJournal($context, 'JE-REV', '2026-07-05', '40.0000', 'REV-ME');
    app(ReverseJournalEntry::class)->handle($original, '2026-07-06', $context['period']->id, 'Correction', $context['user']->id);

    $voided = app(SaveJournalEntry::class)->handle([
        'journal_number' => 'JE-VOID', 'journal_date' => '2026-07-07', 'document_date' => '2026-07-07',
        'fiscal_period_id' => $context['period']->id, 'journal_type' => 'adjustment', 'source_type' => 'manual',
        'reference_number' => 'VOID', 'description' => 'Voided draft', 'lines' => [
            ['account_id' => $context['cash']->id, 'debit' => '9.0000', 'credit' => '0.0000'],
            ['account_id' => $context['capital']->id, 'debit' => '0.0000', 'credit' => '9.0000'],
        ],
    ], $context['user']->id);
    app(TransitionJournalEntry::class)->handle($voided, JournalEntryStatus::Voided, $context['user']->id, 'Entered in error');

    $filters = ['start_date' => '2026-07-01', 'end_date' => '2026-07-31', 'include_descendants' => false];
    $ledger = app(GeneralLedgerReport::class)->ledger($filters, false);
    $journal = app(GeneralLedgerReport::class)->journal($filters, false);

    expect($journal)->toHaveCount(2)
        ->and($journal->pluck('journal_number')->all())->toContain('JE-REV')
        ->and($journal->pluck('journal_number')->all())->not->toContain('JE-VOID')
        ->and($ledger['rows'])->toHaveCount(4)
        ->and($ledger['debit'])->toBe($ledger['credit'])
        ->and($ledger['closing'])->toBe('0.0000');
});

it('filters source references and supports account descendants', function (): void {
    $context = ledgerReportContext();
    $header = Account::query()->create([
        'code' => '1000-X', 'name' => 'Test current assets', 'account_class' => 'asset',
        'account_type' => 'cash', 'normal_balance' => 'debit', 'is_header' => true,
        'is_postable' => false, 'is_active' => true,
    ]);
    $context['cash']->update(['parent_id' => $header->id]);
    postLedgerJournal($context, 'JE-FILTER', '2026-07-05', '15.0000', 'MATCH-123');
    postLedgerJournal($context, 'JE-OTHER', '2026-07-06', '8.0000', 'OTHER');

    $report = app(GeneralLedgerReport::class)->ledger([
        'start_date' => '2026-07-01', 'end_date' => '2026-07-31',
        'account_id' => $header->id, 'include_descendants' => true,
        'source_type' => 'manual', 'reference' => 'MATCH',
    ], false);

    expect($report['rows'])->toHaveCount(1)
        ->and($report['rows']->first()->journalEntry->journal_number)->toBe('JE-FILTER');
});

it('enforces report and sensitive-balance permissions including export', function (): void {
    $context = ledgerReportContext();
    $none = User::factory()->create();
    $viewOnly = User::factory()->create();
    $viewOnly->givePermissionTo(['general-ledger.view', 'account-balances.view']);
    $filters = ['start_date' => '2026-07-01', 'end_date' => '2026-07-31'];

    $this->actingAs($none)->get(route('general-ledger.index', $filters))->assertForbidden();
    $this->actingAs($viewOnly)->get(route('general-ledger.index', $filters))->assertSuccessful();
    $this->actingAs($viewOnly)->get(route('general-ledger.export', $filters))->assertForbidden();
    $this->actingAs($context['user'])->get(route('general-journal.index', $filters))->assertSuccessful();
    $this->actingAs($context['user'])->get(route('account-activity.index', $filters))->assertSuccessful();
    $this->actingAs($context['user'])->get(route('general-ledger.export', $filters))
        ->assertSuccessful()
        ->assertHeader('content-type', 'text/csv; charset=UTF-8');
});
