<?php

use App\Models\Account;
use App\Models\BackupRun;
use App\Models\BusinessProfile;
use App\Models\DocumentSequence;
use App\Models\FiscalPeriod;
use App\Models\FiscalYear;
use App\Models\JournalEntry;
use App\Models\ProductionCutover;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

uses(LazilyRefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->creator = User::factory()->administrator()->create();
    $this->reviewer = User::factory()->administrator()->create();
    $profile = BusinessProfile::factory()->create(['created_by' => $this->creator->id, 'updated_by' => $this->creator->id]);
    $year = FiscalYear::factory()->create(['business_profile_id' => $profile->id, 'starts_on' => '2026-01-01', 'ends_on' => '2026-12-31', 'created_by' => $this->creator->id]);
    $period = FiscalPeriod::factory()->create(['fiscal_year_id' => $year->id, 'starts_on' => '2026-08-01', 'ends_on' => '2026-08-31', 'status' => 'open']);
    $asset = Account::query()->create(['code' => 'CUT-100', 'name' => 'Cutover Cash', 'account_class' => 'asset', 'account_type' => 'cash', 'normal_balance' => 'debit']);
    $equity = Account::query()->create(['code' => 'CUT-300', 'name' => 'Cutover Capital', 'account_class' => 'owner_equity', 'account_type' => 'owner_capital', 'normal_balance' => 'credit']);
    $entry = JournalEntry::query()->create([
        'journal_number' => 'OPEN-CUTOVER', 'journal_date' => '2026-08-01', 'document_date' => '2026-08-01', 'fiscal_period_id' => $period->id,
        'journal_type' => 'opening', 'source_type' => 'manual', 'description' => 'Approved opening balances', 'total_debit' => '100.0000', 'total_credit' => '100.0000',
        'status' => 'posted', 'posted_at' => now(), 'posted_by' => $this->creator->id, 'created_by' => $this->creator->id, 'updated_by' => $this->creator->id,
    ]);
    $entry->lines()->createMany([
        ['account_id' => $asset->id, 'line_number' => 1, 'debit' => '100.0000', 'credit' => '0.0000'],
        ['account_id' => $equity->id, 'line_number' => 2, 'debit' => '0.0000', 'credit' => '100.0000'],
    ]);
    DocumentSequence::query()->create(['business_profile_id' => $profile->id, 'fiscal_year_id' => $year->id, 'fiscal_year_scope' => $year->id, 'document_type' => 'journal_entry', 'prefix' => 'JE-', 'current_number' => 100, 'active' => true, 'created_by' => $this->creator->id, 'updated_by' => $this->creator->id]);
    $this->backup = BackupRun::query()->create(['backup_class' => 'pre_deployment', 'status' => 'verified', 'disk' => 'backups', 'location' => 'protected', 'size_bytes' => 100, 'sha256' => str_repeat('a', 64), 'encrypted' => true, 'offsite_copied' => true, 'started_at' => now(), 'completed_at' => now(), 'verified_at' => now(), 'restore_tested_at' => now(), 'initiated_by' => $this->creator->id]);
});

function cutoverPayload(array $changes = []): array
{
    return array_replace([
        'cutover_date' => '2026-08-01', 'legacy_freeze_reference' => 'LEGACY-FREEZE-2026-08-01',
        'source_documents_reference' => 'OPENING-PACK-001', 'backup_run_id' => test()->backup->id,
        'rollback_rehearsal_reference' => 'RESTORE-TEST-001', 'cash_confirmed' => '1',
        'owner_equity_confirmed' => '1', 'sequence_confirmed' => '1', 'tax_control_confirmed' => '1',
    ], $changes);
}

it('creates approves and activates a complete controlled cutover report', function (): void {
    $this->actingAs($this->creator)->post(route('production-cutovers.store'), cutoverPayload())->assertRedirect()->assertSessionHasNoErrors();
    $cutover = ProductionCutover::query()->sole();

    $this->actingAs($this->reviewer)->patch(route('production-cutovers.approve', $cutover))->assertRedirect()->assertSessionHasNoErrors();
    $cutover->refresh();

    expect($cutover->status)->toBe('approved')->and($cutover->reviewed_by)->toBe($this->reviewer->id)
        ->and($cutover->report_snapshot['trial_balance']['balanced'])->toBeTrue()
        ->and($cutover->report_snapshot['opening_journal_count'])->toBe(1)
        ->and($cutover->report_snapshot['subledgers']['unreconciled'])->toBe([]);

    $this->actingAs($this->creator)->patch(route('production-cutovers.activate', $cutover))->assertRedirect()->assertSessionHasNoErrors();
    expect($cutover->fresh()->status)->toBe('activated')->and($cutover->fresh()->activated_by)->toBe($this->creator->id);
});

it('requires source evidence confirmations and prevents duplicate cutover dates', function (): void {
    $this->actingAs($this->creator)->post(route('production-cutovers.store'), cutoverPayload(['source_documents_reference' => '', 'cash_confirmed' => null]))
        ->assertSessionHasErrors(['source_documents_reference', 'cash_confirmed']);
    $this->post(route('production-cutovers.store'), cutoverPayload())->assertSessionHasNoErrors();
    $this->post(route('production-cutovers.store'), cutoverPayload())->assertSessionHasErrors('cutover_date');
});

it('blocks self approval and rejects an unverified backup gate', function (): void {
    $badBackup = BackupRun::query()->create(['backup_class' => 'pre_deployment', 'status' => 'verified', 'disk' => 'backups', 'location' => 'bad', 'encrypted' => true, 'offsite_copied' => false, 'started_at' => now(), 'verified_at' => now(), 'initiated_by' => $this->creator->id]);
    $this->actingAs($this->creator)->post(route('production-cutovers.store'), cutoverPayload(['backup_run_id' => $badBackup->id]))->assertSessionHasNoErrors();
    $cutover = ProductionCutover::query()->sole();

    $this->patch(route('production-cutovers.approve', $cutover))->assertForbidden();
    $this->actingAs($this->reviewer)->patch(route('production-cutovers.approve', $cutover))->assertSessionHasErrors('status');
    expect($cutover->fresh()->status)->toBe('draft');
});

it('enforces cutover authorization', function (): void {
    $viewer = User::factory()->create();
    $viewer->assignRole('Viewer');

    $this->actingAs($viewer)->get(route('production-cutovers.index'))->assertForbidden();
    $this->post(route('production-cutovers.store'), cutoverPayload())->assertForbidden();
    $this->actingAs($this->creator)->get(route('production-cutovers.index'))->assertSuccessful()->assertSee('Production cutover');
});
