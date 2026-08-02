<?php

use App\Models\AuditLog;
use App\Models\User;
use App\Services\BackupManager;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

uses(LazilyRefreshDatabase::class);

beforeEach(function () {
    config(['audit.capture_during_tests' => true]);
    $this->seed(RolesAndPermissionsSeeder::class);
    Storage::fake('local');
    Storage::fake('backups');
    Storage::fake('offsite');
    $this->backupFixtureRoot = storage_path('framework/testing/backup-'.str()->uuid());
    File::ensureDirectoryExists($this->backupFixtureRoot);
    $database = $this->backupFixtureRoot.'/source.sqlite';
    $pdo = new PDO('sqlite:'.$database);
    $pdo->exec('CREATE TABLE migrations (id INTEGER PRIMARY KEY, migration TEXT, batch INTEGER)');
    $pdo->exec("INSERT INTO migrations (migration, batch) VALUES ('baseline', 1)");
    $pdo->exec('CREATE TABLE recovery_marker (value TEXT)');
    $pdo->exec("INSERT INTO recovery_marker (value) VALUES ('database-restored')");
    config([
        'database.default' => 'sqlite', 'database.connections.sqlite.database' => $database,
        'backup.disk' => 'backups', 'backup.offsite_disk' => 'offsite',
        'backup.encryption_key' => base64_encode(random_bytes(SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_KEYBYTES)),
        'backup.restore_root' => $this->backupFixtureRoot.'/restore-tests',
    ]);
    Storage::disk('local')->put('sales-attachments/receipt.txt', 'attachment-restored');
});

afterEach(function () {
    File::deleteDirectory($this->backupFixtureRoot);
});

test('database private files and safe configuration are encrypted copied and verified', function () {
    $run = app(BackupManager::class)->create('daily', User::factory()->administrator()->create());

    expect($run->status)->toBe('verified')->and($run->encrypted)->toBeTrue()->and($run->offsite_copied)->toBeTrue()
        ->and($run->size_bytes)->toBeGreaterThan(0)->and($run->sha256)->toHaveLength(64)
        ->and(Storage::disk('backups')->exists($run->location))->toBeTrue()
        ->and(Storage::disk('offsite')->exists($run->location))->toBeTrue()
        ->and(AuditLog::query()->where('event', 'backup.verified')->where('subject_id', $run->id)->exists())->toBeTrue();
});

test('hash verification detects a corrupt backup', function () {
    $run = app(BackupManager::class)->create('daily');
    Storage::disk('backups')->put($run->location, 'corrupt');

    expect(fn () => app(BackupManager::class)->verify($run))->toThrow(RuntimeException::class, 'hash verification failed')
        ->and($run->fresh()->status)->toBe('corrupt')
        ->and(AuditLog::query()->where('event', 'backup.corrupt')->where('subject_id', $run->id)->exists())->toBeTrue();
});

test('restore exercise recovers database attachments and safe configuration in isolation', function () {
    $run = app(BackupManager::class)->create('weekly');
    $restored = app(BackupManager::class)->restoreExercise($run, 'quarterly-2026-q3');
    $pdo = new PDO('sqlite:'.$restored.'/database/database.sqlite');

    expect($pdo->query('SELECT value FROM recovery_marker')->fetchColumn())->toBe('database-restored')
        ->and(File::get($restored.'/private/sales-attachments/receipt.txt'))->toBe('attachment-restored')
        ->and(File::exists($restored.'/configuration/.env'))->toBeFalse()
        ->and(File::exists($restored.'/configuration/.env.example'))->toBeTrue()
        ->and($run->fresh()->restore_tested_at)->not->toBeNull()
        ->and(AuditLog::query()->where('event', 'backup.restore_tested')->where('subject_id', $run->id)->exists())->toBeTrue();
});

test('invalid keys and unsafe restore destinations are rejected', function () {
    config(['backup.encryption_key' => 'invalid']);
    expect(fn () => app(BackupManager::class)->create('daily'))->toThrow(RuntimeException::class, 'BACKUP_ENCRYPTION_KEY');

    config(['backup.encryption_key' => base64_encode(random_bytes(SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_KEYBYTES))]);
    $run = app(BackupManager::class)->create('daily');
    expect(fn () => app(BackupManager::class)->restoreExercise($run, '../production'))->toThrow(RuntimeException::class, 'Invalid restore exercise');
});

test('backup status is owner restricted and does not expose storage locations', function () {
    $run = app(BackupManager::class)->create('daily');
    $viewer = User::factory()->create();
    $viewer->assignRole('Viewer');
    $admin = User::factory()->administrator()->create();

    $this->actingAs($viewer)->get(route('backup-runs.index'))->assertForbidden();
    $this->actingAs($admin)->get(route('backup-runs.index'))->assertSuccessful()
        ->assertSee('Backup status')->assertDontSee($run->location);
});
