<?php

use App\Actions\CorrectJournalEntry;
use App\Actions\ReverseJournalEntry;
use App\Actions\SaveJournalEntry;
use App\Actions\TransitionJournalEntry;
use App\Enums\JournalEntryStatus;
use App\Enums\JournalEntryType;
use App\Models\Account;
use App\Models\FiscalPeriod;
use App\Models\FiscalYear;
use App\Models\JournalEntry;
use App\Models\User;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

uses(LazilyRefreshDatabase::class);

function reversalContext(): array
{
    test()->seed([RolesAndPermissionsSeeder::class, ChartOfAccountsSeeder::class]);
    $user = User::factory()->create();
    $user->assignRole('Bookkeeper');
    $year = FiscalYear::factory()->create(['starts_on' => '2026-01-01', 'ends_on' => '2026-12-31']);
    $july = FiscalPeriod::factory()->create([
        'fiscal_year_id' => $year->id,
        'name' => 'July 2026',
        'starts_on' => '2026-07-01',
        'ends_on' => '2026-07-31',
        'calendar_month' => 7,
        'calendar_quarter' => 3,
        'status' => 'open',
    ]);
    $august = FiscalPeriod::factory()->create([
        'fiscal_year_id' => $year->id,
        'name' => 'August 2026',
        'starts_on' => '2026-08-01',
        'ends_on' => '2026-08-31',
        'calendar_month' => 8,
        'calendar_quarter' => 3,
        'status' => 'open',
    ]);
    $accounts = Account::query()->whereIn('code', ['1010', '3010'])->orderBy('code')->get();

    return compact('user', 'july', 'august', 'accounts');
}

function postedReversalJournal(array $context, array $overrides = []): JournalEntry
{
    $data = array_replace([
        'journal_number' => 'JE-REV-001',
        'journal_date' => '2026-07-31',
        'document_date' => '2026-07-31',
        'fiscal_period_id' => $context['july']->id,
        'journal_type' => 'adjustment',
        'source_type' => 'manual',
        'source_id' => null,
        'reference_number' => 'ADJ-001',
        'description' => 'Accrued adjustment',
        'lines' => [
            ['account_id' => $context['accounts'][0]->id, 'description' => 'Debit line', 'debit' => '125.4321', 'credit' => '0.0000'],
            ['account_id' => $context['accounts'][1]->id, 'description' => 'Credit line', 'debit' => '0.0000', 'credit' => '125.4321'],
        ],
    ], $overrides);
    $entry = app(SaveJournalEntry::class)->handle($data, $context['user']->id);

    return app(TransitionJournalEntry::class)->handle($entry, JournalEntryStatus::Posted, $context['user']->id);
}

it('posts a balanced full reversal without modifying original lines or source reference', function (): void {
    $context = reversalContext();
    $original = postedReversalJournal($context, ['source_type' => 'sales_invoice', 'source_id' => 77]);
    $originalLines = $original->lines()->get()->map->only(['account_id', 'debit', 'credit'])->all();

    $reversal = app(ReverseJournalEntry::class)->handle(
        $original,
        '2026-07-31',
        $context['july']->id,
        'Incorrect source posting',
        $context['user']->id,
    );

    expect($original->fresh()->status)->toBe(JournalEntryStatus::Reversed)
        ->and($original->fresh()->reversal_entry_id)->toBe($reversal->id)
        ->and($original->fresh()->reversal_reason)->toBe('Incorrect source posting')
        ->and($original->fresh()->lines()->get()->map->only(['account_id', 'debit', 'credit'])->all())->toBe($originalLines)
        ->and($reversal->status)->toBe(JournalEntryStatus::Posted)
        ->and($reversal->reverses_entry_id)->toBe($original->id)
        ->and($reversal->reference_number)->toBe($original->journal_number)
        ->and($reversal->total_debit)->toBe('125.4321')
        ->and($reversal->total_credit)->toBe('125.4321')
        ->and($reversal->lines[0]->debit)->toBe('0.0000')
        ->and($reversal->lines[0]->credit)->toBe('125.4321');

    expect(fn () => app(ReverseJournalEntry::class)->handle(
        $original,
        '2026-07-31',
        $context['july']->id,
        'Duplicate',
        $context['user']->id,
    ))->toThrow(DomainException::class, 'unreversed');
});

it('rejects closed-period reversals without creating partial entries', function (): void {
    $context = reversalContext();
    $original = postedReversalJournal($context);
    $context['july']->update(['status' => 'closed']);

    expect(fn () => app(ReverseJournalEntry::class)->handle(
        $original,
        '2026-07-31',
        $context['july']->id,
        'Closed period attempt',
        $context['user']->id,
    ))->toThrow(DomainException::class, 'open fiscal period')
        ->and(JournalEntry::query()->count())->toBe(1)
        ->and($original->fresh()->status)->toBe(JournalEntryStatus::Posted);
});

it('creates a traceable reversal and editable correcting draft atomically', function (): void {
    $context = reversalContext();
    $original = postedReversalJournal($context);

    $result = app(CorrectJournalEntry::class)->handle(
        $original,
        '2026-07-31',
        $context['july']->id,
        'Correct account classification',
        $context['user']->id,
    );

    expect(JournalEntry::query()->count())->toBe(3)
        ->and($result['reversal']->status)->toBe(JournalEntryStatus::Posted)
        ->and($result['replacement']->status)->toBe(JournalEntryStatus::Draft)
        ->and($result['replacement']->correction_of_id)->toBe($original->id)
        ->and($result['replacement']->reference_number)->toBe($original->journal_number)
        ->and($original->fresh()->correctionEntry->is($result['replacement']))->toBeTrue();

    $result['replacement']->update(['description' => 'Corrected classification']);
    expect($result['replacement']->fresh()->description)->toBe('Corrected classification');
});

it('auto-reverses only into the next open future period', function (): void {
    $context = reversalContext();
    $original = postedReversalJournal($context);

    $reversal = app(ReverseJournalEntry::class)->handle(
        $original,
        '2026-08-01',
        $context['august']->id,
        'Reverse accrual next month',
        $context['user']->id,
        true,
    );

    expect($reversal->is_auto_reversal)->toBeTrue()
        ->and($reversal->journal_date->toDateString())->toBe('2026-08-01')
        ->and($original->fresh()->auto_reverse_on->toDateString())->toBe('2026-08-01');

    $second = postedReversalJournal($context, ['journal_number' => 'JE-REV-002']);
    expect(fn () => app(ReverseJournalEntry::class)->handle(
        $second,
        '2026-07-31',
        $context['july']->id,
        'Invalid auto reversal',
        $context['user']->id,
        true,
    ))->toThrow(DomainException::class, 'next open period');
});

it('enforces reversal adjustment opening and auto-reversal permissions', function (): void {
    $context = reversalContext();
    $original = postedReversalJournal($context);
    $viewer = User::factory()->create();
    $viewer->assignRole('Viewer');

    $this->actingAs($viewer)->post(route('journal-entries.reverse', $original), [
        'reversal_date' => '2026-07-31',
        'fiscal_period_id' => $context['july']->id,
        'reason' => 'Unauthorized',
    ])->assertForbidden();
    $this->actingAs($viewer)->post(route('journal-entries.correct', $original), [
        'correction_date' => '2026-07-31',
        'fiscal_period_id' => $context['july']->id,
        'reason' => 'Unauthorized correction',
    ])->assertForbidden();

    $limited = User::factory()->create();
    $limited->givePermissionTo(['journals.view', 'journals.create', 'journals.reverse']);
    $this->actingAs($limited)->post(route('journal-entries.reverse', $original), [
        'reversal_date' => '2026-08-01',
        'fiscal_period_id' => $context['august']->id,
        'reason' => 'Unauthorized automatic reversal',
        'auto_reverse' => true,
    ])->assertForbidden();

    $this->actingAs($limited)->post(route('journal-entries.store'), [
        'journal_number' => 'OPEN-001',
        'journal_date' => '2026-07-01',
        'document_date' => '2026-07-01',
        'fiscal_period_id' => $context['july']->id,
        'journal_type' => 'opening',
        'source_type' => 'manual',
        'description' => 'Opening entry',
        'lines' => [
            ['account_id' => $context['accounts'][0]->id, 'debit' => '1.0000', 'credit' => '0.0000'],
            ['account_id' => $context['accounts'][1]->id, 'debit' => '0.0000', 'credit' => '1.0000'],
        ],
    ])->assertForbidden();

    $this->actingAs($limited)->post(route('journal-entries.store'), [
        'journal_number' => 'ADJ-UNAUTHORIZED',
        'journal_date' => '2026-07-31',
        'document_date' => '2026-07-31',
        'fiscal_period_id' => $context['july']->id,
        'journal_type' => 'adjustment',
        'source_type' => 'manual',
        'description' => 'Unauthorized adjustment',
        'lines' => [
            ['account_id' => $context['accounts'][0]->id, 'debit' => '1.0000', 'credit' => '0.0000'],
            ['account_id' => $context['accounts'][1]->id, 'debit' => '0.0000', 'credit' => '1.0000'],
        ],
    ])->assertForbidden();

    $this->actingAs($context['user'])->post(route('journal-entries.store'), [
        'journal_number' => 'OPEN-AUTHORIZED',
        'journal_date' => '2026-07-01',
        'document_date' => '2026-07-01',
        'fiscal_period_id' => $context['july']->id,
        'journal_type' => 'opening',
        'source_type' => 'manual',
        'description' => 'Authorized opening entry',
        'lines' => [
            ['account_id' => $context['accounts'][0]->id, 'debit' => '10.0000', 'credit' => '0.0000'],
            ['account_id' => $context['accounts'][1]->id, 'debit' => '0.0000', 'credit' => '10.0000'],
        ],
    ])->assertRedirect();
    expect(JournalEntry::query()->where('journal_number', 'OPEN-AUTHORIZED')->value('journal_type'))->toBe(JournalEntryType::Opening);
});
