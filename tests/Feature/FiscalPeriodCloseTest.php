<?php

use App\Actions\SaveJournalEntry;
use App\Actions\TransitionJournalEntry;
use App\Enums\JournalEntryStatus;
use App\Models\Account;
use App\Models\FiscalPeriod;
use App\Models\FiscalPeriodEvent;
use App\Models\FiscalYear;
use App\Models\SourcePosting;
use App\Models\User;
use App\Services\PeriodCloseChecklist;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

uses(LazilyRefreshDatabase::class);

function periodCloseContext(): array
{
    test()->seed([RolesAndPermissionsSeeder::class, ChartOfAccountsSeeder::class]);
    $user = User::factory()->administrator()->create();
    $year = FiscalYear::factory()->create(['starts_on' => '2026-01-01', 'ends_on' => '2026-12-31']);
    $period = FiscalPeriod::factory()->create([
        'fiscal_year_id' => $year->id, 'name' => 'July 2026',
        'starts_on' => '2026-07-01', 'ends_on' => '2026-07-31',
        'calendar_year' => 2026, 'calendar_month' => 7, 'calendar_quarter' => 3, 'status' => 'open',
    ]);
    $accounts = Account::query()->whereIn('code', ['1100', '3010', '6010'])->get()->keyBy('code');

    return compact('user', 'year', 'period', 'accounts');
}

function periodJournal(array $context, string $number, string $type = 'sales', string $amount = '10.0000')
{
    return app(SaveJournalEntry::class)->handle([
        'journal_number' => $number, 'journal_date' => '2026-07-15', 'document_date' => '2026-07-15',
        'fiscal_period_id' => $context['period']->id, 'journal_type' => $type, 'source_type' => 'manual',
        'reference_number' => $number, 'description' => $number, 'lines' => [
            ['account_id' => $context['accounts'][$type === 'adjustment' ? '6010' : '1100']->id, 'debit' => $amount, 'credit' => '0.0000'],
            ['account_id' => $context['accounts']['3010']->id, 'debit' => '0.0000', 'credit' => $amount],
        ],
    ], $context['user']->id);
}

it('generates an explicit passing checklist and records close audit metadata', function (): void {
    $context = periodCloseContext();
    $checklist = app(PeriodCloseChecklist::class)->generate($context['period']);

    expect(collect($checklist)->every(fn (array $item): bool => $item['passed']))->toBeTrue();

    $this->actingAs($context['user'])->get(route('fiscal-periods.preclose', $context['period']))
        ->assertSuccessful()->assertSee('Pre-close checklist');
    $viewer = User::factory()->create();
    $viewer->assignRole('Viewer');
    $this->actingAs($viewer)->get(route('fiscal-periods.show', $context['period']))->assertSuccessful();
    $this->actingAs($context['user'])->patch(route('fiscal-periods.status.update', $context['period']), [
        'status' => 'closed', 'lock_version' => 0, 'notes' => 'Month reviewed and reconciled.',
    ])->assertSessionHasNoErrors();

    $closed = $context['period']->fresh();
    expect($closed->status)->toBe('closed')
        ->and($closed->closed_by)->toBe($context['user']->id)
        ->and($closed->close_notes)->toBe('Month reviewed and reconciled.')
        ->and($closed->close_checklist)->toBeArray()
        ->and($closed->lock_version)->toBe(1)
        ->and(FiscalPeriodEvent::query()->sole()->action)->toBe('closed');
});

it('blocks close for unposted journals and failed source postings', function (): void {
    $context = periodCloseContext();
    periodJournal($context, 'DRAFT-BLOCKER');
    SourcePosting::query()->create([
        'source_type' => 'sales_invoice', 'source_id' => 999, 'status' => 'failed',
        'failure_reason' => 'Mapping missing',
    ]);

    $checklist = app(PeriodCloseChecklist::class)->generate($context['period']);
    expect($checklist['unposted_journals']['count'])->toBe(1)
        ->and($checklist['failed_source_postings']['count'])->toBe(1);

    $this->actingAs($context['user'])->patch(route('fiscal-periods.status.update', $context['period']), [
        'status' => 'closed', 'lock_version' => 0,
    ])->assertSessionHasErrors('status');
    expect($context['period']->fresh()->status)->toBe('open');
});

it('blocks critical subledger differences', function (): void {
    $context = periodCloseContext();
    $posted = periodJournal($context, 'AR-DIFFERENCE');
    app(TransitionJournalEntry::class)->handle($posted, JournalEntryStatus::Posted, $context['user']->id);

    $checklist = app(PeriodCloseChecklist::class)->generate($context['period']);
    expect($checklist['ar_difference']['count'])->toBe(1);
    $this->actingAs($context['user'])->patch(route('fiscal-periods.status.update', $context['period']), [
        'status' => 'closed', 'lock_version' => 0,
    ])->assertSessionHasErrors('status');
});

it('permits only a documented open-adjustment override', function (): void {
    $context = periodCloseContext();
    periodJournal($context, 'OPEN-ADJUSTMENT', 'adjustment');
    $checklist = app(PeriodCloseChecklist::class)->generate($context['period']);
    expect(collect($checklist)->reject(fn (array $item): bool => $item['passed'])->keys()->all())
        ->toBe(['open_adjustments']);
    $this->actingAs($context['user'])->patch(route('fiscal-periods.status.update', $context['period']), [
        'status' => 'closed', 'lock_version' => 0, 'override_open_adjustments' => 1,
    ])->assertSessionHasErrors('notes');
    $this->actingAs($context['user'])->patch(route('fiscal-periods.status.update', $context['period']), [
        'status' => 'closed', 'lock_version' => 0, 'override_open_adjustments' => 1,
        'notes' => 'Adjustment approved for next-period completion.',
    ])->assertSessionHasNoErrors();
    expect($context['period']->fresh()->close_overrides)->toBe(['open_adjustments']);
});

it('locks after close and only elevated authorized users may reopen with a reason', function (): void {
    $context = periodCloseContext();
    $this->actingAs($context['user'])->patch(route('fiscal-periods.status.update', $context['period']), [
        'status' => 'closed', 'lock_version' => 0,
    ])->assertSessionHasNoErrors();
    $this->actingAs($context['user'])->patch(route('fiscal-periods.status.update', $context['period']), [
        'status' => 'locked', 'lock_version' => 1, 'notes' => 'Final review complete.',
    ])->assertSessionHasNoErrors();

    $bookkeeper = User::factory()->create();
    $bookkeeper->assignRole('Bookkeeper');
    $this->actingAs($bookkeeper)->patch(route('fiscal-periods.status.update', $context['period']), [
        'status' => 'open', 'lock_version' => 2, 'notes' => 'Unauthorized reopen.',
    ])->assertForbidden();
    $this->actingAs($context['user'])->patch(route('fiscal-periods.status.update', $context['period']), [
        'status' => 'open', 'lock_version' => 2,
    ])->assertSessionHasErrors('notes');
    $this->actingAs($context['user'])->patch(route('fiscal-periods.status.update', $context['period']), [
        'status' => 'open', 'lock_version' => 2, 'notes' => 'Approved correction required.',
    ])->assertSessionHasNoErrors();

    $reopened = $context['period']->fresh();
    expect($reopened->status)->toBe('open')
        ->and($reopened->reopened_by)->toBe($context['user']->id)
        ->and($reopened->reopen_reason)->toBe('Approved correction required.')
        ->and($reopened->events()->count())->toBe(3);
});

it('rejects posting to closed and locked periods and detects stale concurrent transitions', function (): void {
    $context = periodCloseContext();
    $draft = periodJournal($context, 'POST-BLOCKED', 'adjustment');
    $context['period']->forceFill(['status' => 'closed'])->save();
    expect(fn () => app(TransitionJournalEntry::class)->handle($draft, JournalEntryStatus::Posted, $context['user']->id))
        ->toThrow(DomainException::class, 'open fiscal period');
    $context['period']->forceFill(['status' => 'locked'])->save();
    expect(fn () => app(TransitionJournalEntry::class)->handle($draft, JournalEntryStatus::Posted, $context['user']->id))
        ->toThrow(DomainException::class, 'open fiscal period');

    $clean = periodCloseContext();
    $this->actingAs($clean['user'])->patch(route('fiscal-periods.status.update', $clean['period']), [
        'status' => 'closed', 'lock_version' => 0,
    ])->assertSessionHasNoErrors();
    $this->actingAs($clean['user'])->patch(route('fiscal-periods.status.update', $clean['period']), [
        'status' => 'locked', 'lock_version' => 0,
    ])->assertSessionHasErrors('status');
    expect($clean['period']->fresh()->status)->toBe('closed');
});
