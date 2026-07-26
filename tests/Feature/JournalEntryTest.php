<?php

use App\Actions\SaveJournalEntry;
use App\Actions\TransitionJournalEntry;
use App\Enums\JournalEntryStatus;
use App\Models\Account;
use App\Models\FiscalPeriod;
use App\Models\FiscalYear;
use App\Models\JournalEntry;
use App\Models\User;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

uses(LazilyRefreshDatabase::class);

function journalContext(string $periodStatus = 'open'): array
{
    test()->seed([RolesAndPermissionsSeeder::class, ChartOfAccountsSeeder::class]);
    $user = User::factory()->create();
    $user->assignRole('Bookkeeper');
    $year = FiscalYear::factory()->create(['starts_on' => '2026-01-01', 'ends_on' => '2026-12-31']);
    $period = FiscalPeriod::factory()->create(['fiscal_year_id' => $year->id, 'starts_on' => '2026-07-01', 'ends_on' => '2026-07-31', 'status' => $periodStatus]);
    $accounts = Account::query()->whereIn('code', ['1010', '3010'])->orderBy('code')->get();

    return compact('user', 'period', 'accounts');
}

function journalData(array $context, array $overrides = []): array
{
    return array_replace_recursive([
        'journal_number' => 'JE-2026-000001', 'journal_date' => '2026-07-15', 'document_date' => '2026-07-14',
        'fiscal_period_id' => $context['period']->id, 'journal_type' => 'adjustment', 'source_type' => 'manual',
        'source_id' => null, 'reference_number' => 'REF-1', 'description' => 'Owner contribution',
        'lines' => [
            ['account_id' => $context['accounts'][0]->id, 'description' => 'Cash', 'debit' => '1000.1234', 'credit' => '0.0000'],
            ['account_id' => $context['accounts'][1]->id, 'description' => 'Capital', 'debit' => '0.0000', 'credit' => '1000.1234'],
        ],
    ], $overrides);
}

it('saves precise balanced drafts and posts them in an open period', function (): void {
    $context = journalContext();
    $this->actingAs($context['user'])->post(route('journal-entries.store'), journalData($context))->assertRedirect();
    $entry = JournalEntry::query()->sole();
    expect($entry->total_debit)->toBe('1000.1234')->and($entry->total_credit)->toBe('1000.1234')->and($entry->lines)->toHaveCount(2);

    $this->actingAs($context['user'])->patch(route('journal-entries.transition', $entry), ['status' => 'posted'])->assertRedirect();
    expect($entry->fresh()->status)->toBe(JournalEntryStatus::Posted)->and($entry->fresh()->posted_by)->toBe($context['user']->id);
});

it('rejects debit and credit together and zero value lines', function (): void {
    $context = journalContext();
    $data = journalData($context);
    $data['lines'][0] = array_replace($data['lines'][0], ['debit' => '1.0000', 'credit' => '1.0000']);
    $data['lines'][1] = array_replace($data['lines'][1], ['debit' => '0.0000', 'credit' => '0.0000']);

    $this->actingAs($context['user'])->post(route('journal-entries.store'), $data)
        ->assertSessionHasErrors(['lines.0.debit', 'lines.1.debit']);
});

it('rejects unbalanced and closed-period posting', function (): void {
    $context = journalContext();
    $entry = app(SaveJournalEntry::class)->handle(journalData($context, ['lines' => [
        ['account_id' => $context['accounts'][0]->id, 'debit' => '2.0000', 'credit' => '0.0000'],
        ['account_id' => $context['accounts'][1]->id, 'debit' => '0.0000', 'credit' => '1.0000'],
    ]]), $context['user']->id);
    expect(fn () => app(TransitionJournalEntry::class)->handle($entry, JournalEntryStatus::Posted, $context['user']->id))->toThrow(DomainException::class, 'balanced');

    $entry->update(['total_credit' => '2.0000']);
    $context['period']->update(['status' => 'closed']);
    expect(fn () => app(TransitionJournalEntry::class)->handle($entry, JournalEntryStatus::Posted, $context['user']->id))->toThrow(DomainException::class, 'open fiscal period');
});

it('makes posted entries immutable and prevents hard deletion', function (): void {
    $context = journalContext();
    $entry = app(SaveJournalEntry::class)->handle(journalData($context), $context['user']->id);
    app(TransitionJournalEntry::class)->handle($entry, JournalEntryStatus::Posted, $context['user']->id);

    expect(fn () => $entry->refresh()->update(['description' => 'Changed']))->toThrow(DomainException::class)
        ->and(fn () => $entry->refresh()->delete())->toThrow(DomainException::class);
});

it('enforces unique automatic source links and authorization', function (): void {
    $context = journalContext();
    $data = journalData($context, ['source_type' => 'sales_invoice', 'source_id' => 55]);
    app(SaveJournalEntry::class)->handle($data, $context['user']->id);

    $this->actingAs($context['user'])->post(route('journal-entries.store'), array_replace($data, ['journal_number' => 'JE-2026-000002']))
        ->assertSessionHasErrors('source_id');

    $viewer = User::factory()->create();
    $viewer->assignRole('Viewer');
    $this->actingAs($viewer)->get(route('journal-entries.index'))->assertSuccessful();
    $this->actingAs($viewer)->post(route('journal-entries.store'), journalData($context))->assertForbidden();
});
