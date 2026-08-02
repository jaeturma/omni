<?php

namespace App\Services;

use App\Models\BackupRun;
use App\Models\User;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PDO;
use RuntimeException;
use Symfony\Component\Process\Process;
use Throwable;
use ZipArchive;

class BackupManager
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function create(string $class, ?User $actor = null): BackupRun
    {
        $this->validateClass($class);
        $diskName = (string) config('backup.disk', 'backups');
        $run = BackupRun::query()->create(['backup_class' => $class, 'status' => 'running', 'disk' => $diskName, 'location' => 'pending', 'started_at' => now(), 'initiated_by' => $actor?->id]);
        $temporary = storage_path('app/backup-tmp/'.Str::uuid());
        File::ensureDirectoryExists($temporary);

        try {
            $archive = $temporary.'/backup.zip';
            $this->buildArchive($archive, $temporary);
            $location = now()->format('Y/m').'/'.$class.'-'.now()->format('Ymd-His').'-'.$run->id.'.zip.enc';
            $encrypted = $temporary.'/backup.zip.enc';
            $this->encrypt($archive, $encrypted);
            $stream = fopen($encrypted, 'rb');
            throw_unless(is_resource($stream) && $this->disk()->writeStream($location, $stream), RuntimeException::class, 'Backup archive could not be stored.');
            fclose($stream);
            $size = filesize($encrypted);
            $hash = hash_file('sha256', $encrypted);
            throw_unless(is_int($size) && is_string($hash), RuntimeException::class, 'Backup metadata could not be calculated.');
            $offsite = $this->copyOffsite($location);
            $run->update(['status' => 'completed', 'location' => $location, 'size_bytes' => $size, 'sha256' => $hash, 'offsite_copied' => $offsite, 'completed_at' => now()]);
            $this->audit->log('backup.completed', $run, after: ['class' => $class, 'size_bytes' => $size, 'sha256' => $hash, 'offsite_copied' => $offsite]);

            $verified = $this->verify($run);
            $this->pruneExpired();

            return $verified;
        } catch (Throwable $exception) {
            $run->update(['status' => 'failed', 'failure_reason' => Str::limit($exception->getMessage(), 2000), 'completed_at' => now()]);
            $this->audit->log('backup.failed', $run, reason: $run->failure_reason);
            throw $exception;
        } finally {
            File::deleteDirectory($temporary);
        }
    }

    public function verify(BackupRun $run): BackupRun
    {
        throw_unless($run->status === 'completed' || $run->status === 'verified', RuntimeException::class, 'Only completed backups can be verified.');
        $temporary = storage_path('app/backup-tmp/verify-'.Str::uuid());
        File::ensureDirectoryExists($temporary);
        try {
            $encrypted = $temporary.'/backup.enc';
            $this->copyFromDisk($run, $encrypted);
            throw_unless(hash_equals((string) $run->sha256, hash_file('sha256', $encrypted)), RuntimeException::class, 'Backup hash verification failed.');
            $archive = $temporary.'/backup.zip';
            $this->decrypt($encrypted, $archive);
            $zip = new ZipArchive;
            throw_unless($zip->open($archive) === true && $zip->locateName('manifest.json') !== false && $zip->locateName('database/'.basename($this->databaseFilename())) !== false, RuntimeException::class, 'Backup archive is unreadable or incomplete.');
            $zip->close();
            $run->update(['status' => 'verified', 'verified_at' => now(), 'failure_reason' => null]);
            $this->audit->log('backup.verified', $run, after: ['sha256' => $run->sha256]);

            return $run->refresh();
        } catch (Throwable $exception) {
            $run->update(['status' => 'corrupt', 'failure_reason' => Str::limit($exception->getMessage(), 2000)]);
            $this->audit->log('backup.corrupt', $run, reason: $run->failure_reason);
            throw $exception;
        } finally {
            File::deleteDirectory($temporary);
        }
    }

    public function restoreExercise(BackupRun $run, string $exerciseId, ?string $targetDatabase = null): string
    {
        throw_unless($run->status === 'verified', RuntimeException::class, 'Restore exercises require a verified backup.');
        throw_unless(preg_match('/\A[a-zA-Z0-9_-]+\z/', $exerciseId) === 1, RuntimeException::class, 'Invalid restore exercise identifier.');
        $root = rtrim((string) config('backup.restore_root'), DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.$exerciseId;
        throw_if(File::exists($root), RuntimeException::class, 'Restore exercise destination already exists.');
        File::ensureDirectoryExists($root);
        $encrypted = $root.'/backup.enc';
        $archive = $root.'/backup.zip';
        try {
            $this->copyFromDisk($run, $encrypted);
            throw_unless(hash_equals((string) $run->sha256, hash_file('sha256', $encrypted)), RuntimeException::class, 'Backup hash verification failed before restore.');
            $this->decrypt($encrypted, $archive);
            $zip = new ZipArchive;
            throw_unless($zip->open($archive) === true && $zip->extractTo($root.'/restored'), RuntimeException::class, 'Backup extraction failed.');
            $zip->close();
            $this->smokeTestRestoredDatabase($root.'/restored/database/'.$this->databaseFilename(), $targetDatabase);
            File::delete([$encrypted, $archive]);
            $run->update(['restore_tested_at' => now()]);
            $this->audit->log('backup.restore_tested', $run, after: ['exercise_id' => $exerciseId]);

            return $root.'/restored';
        } catch (Throwable $exception) {
            File::deleteDirectory($root);
            $this->audit->log('backup.restore_failed', $run, reason: $exception->getMessage(), metadata: ['exercise_id' => $exerciseId]);
            throw $exception;
        }
    }

    public function pruneExpired(): int
    {
        $pruned = 0;
        foreach ((array) config('backup.retention_days') as $class => $days) {
            BackupRun::query()->where('backup_class', $class)->whereIn('status', ['completed', 'verified', 'corrupt'])->where('started_at', '<', now()->subDays((int) $days))->each(function (BackupRun $run) use (&$pruned): void {
                Storage::disk($run->disk)->delete($run->location);
                $run->update(['status' => 'pruned']);
                $this->audit->log('backup.pruned', $run, after: ['backup_class' => $run->backup_class]);
                $pruned++;
            });
        }

        return $pruned;
    }

    private function buildArchive(string $archive, string $temporary): void
    {
        $database = $temporary.'/'.$this->databaseFilename();
        $this->dumpDatabase($database);
        $zip = new ZipArchive;
        throw_unless($zip->open($archive, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true, RuntimeException::class, 'Backup archive could not be created.');
        $zip->addFile($database, 'database/'.basename($database));
        foreach (File::allFiles(Storage::disk('local')->path('')) as $file) {
            $zip->addFile($file->getPathname(), 'private/'.str_replace('\\', '/', $file->getRelativePathname()));
        }
        $zip->addFile(base_path('.env.example'), 'configuration/.env.example');
        foreach (File::files(config_path()) as $file) {
            $zip->addFile($file->getPathname(), 'configuration/config/'.$file->getFilename());
        }
        $zip->addFromString('manifest.json', json_encode(['created_at' => now()->toIso8601String(), 'database_connection' => config('database.default'), 'private_files' => count(File::allFiles(Storage::disk('local')->path(''))), 'contains_secrets' => false], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT));
        throw_unless($zip->close(), RuntimeException::class, 'Backup archive could not be finalized.');
    }

    private function dumpDatabase(string $destination): void
    {
        $connection = (string) config('database.default');
        if ($connection === 'sqlite') {
            $source = (string) config('database.connections.sqlite.database');
            throw_unless(is_file($source) && copy($source, $destination), RuntimeException::class, 'SQLite backup could not be created.');

            return;
        }
        throw_unless($connection === 'mysql', RuntimeException::class, 'Only MySQL and SQLite backup drivers are supported.');
        $mysql = config('database.connections.mysql');
        $process = new Process([(string) config('backup.mysqldump_binary'), '--single-transaction', '--quick', '--routines', '--triggers', '--host='.(string) $mysql['host'], '--port='.(string) $mysql['port'], '--user='.(string) $mysql['username'], '--result-file='.$destination, (string) $mysql['database']], null, ['MYSQL_PWD' => (string) $mysql['password']], null, 600);
        $process->mustRun();
        throw_unless(is_file($destination) && filesize($destination) > 0, RuntimeException::class, 'MySQL dump is empty.');
    }

    private function smokeTestRestoredDatabase(string $dump, ?string $targetDatabase): void
    {
        if (config('database.default') === 'sqlite') {
            $pdo = new PDO('sqlite:'.$dump);
            throw_unless((int) $pdo->query('SELECT COUNT(*) FROM migrations')->fetchColumn() > 0, RuntimeException::class, 'Restored SQLite database failed its migrations smoke test.');

            return;
        }
        $mysql = config('database.connections.mysql');
        throw_unless(filled($targetDatabase) && $targetDatabase !== $mysql['database'], RuntimeException::class, 'An isolated, non-production MySQL database is required.');
        $arguments = [(string) config('backup.mysql_binary'), '--host='.(string) $mysql['host'], '--port='.(string) $mysql['port'], '--user='.(string) $mysql['username'], '--database='.$targetDatabase];
        $restore = new Process($arguments, null, ['MYSQL_PWD' => (string) $mysql['password']], null, 600);
        $input = fopen($dump, 'rb');
        throw_unless(is_resource($input), RuntimeException::class, 'Restored MySQL dump cannot be opened.');
        $restore->setInput($input)->mustRun();
        fclose($input);
        $smoke = new Process([...$arguments, '--batch', '--skip-column-names', '--execute=SELECT COUNT(*) FROM migrations'], null, ['MYSQL_PWD' => (string) $mysql['password']], null, 60);
        $smoke->mustRun();
        throw_unless((int) trim($smoke->getOutput()) > 0, RuntimeException::class, 'Restored MySQL database failed its migrations smoke test.');
    }

    private function encrypt(string $source, string $destination): void
    {
        $key = $this->key();
        [$state, $header] = sodium_crypto_secretstream_xchacha20poly1305_init_push($key);
        $input = fopen($source, 'rb');
        $output = fopen($destination, 'wb');
        throw_unless(is_resource($input) && is_resource($output), RuntimeException::class, 'Backup encryption streams could not be opened.');
        fwrite($output, $header);
        while (! feof($input)) {
            $chunk = fread($input, 1024 * 1024);
            throw_unless(is_string($chunk), RuntimeException::class, 'Backup archive could not be read.');
            $tag = feof($input) ? SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_FINAL : SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_MESSAGE;
            $cipher = sodium_crypto_secretstream_xchacha20poly1305_push($state, $chunk, '', $tag);
            fwrite($output, pack('N', strlen($cipher)).$cipher);
        }
        fclose($input);
        fclose($output);
    }

    private function decrypt(string $source, string $destination): void
    {
        $input = fopen($source, 'rb');
        $output = fopen($destination, 'wb');
        throw_unless(is_resource($input) && is_resource($output), RuntimeException::class, 'Backup decryption streams could not be opened.');
        $header = fread($input, SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_HEADERBYTES);
        throw_unless(is_string($header) && strlen($header) === SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_HEADERBYTES, RuntimeException::class, 'Encrypted backup header is invalid.');
        $state = sodium_crypto_secretstream_xchacha20poly1305_init_pull($header, $this->key());
        $final = false;
        while (! feof($input)) {
            $lengthBytes = fread($input, 4);
            if ($lengthBytes === '') {
                break;
            }
            throw_unless(is_string($lengthBytes) && strlen($lengthBytes) === 4, RuntimeException::class, 'Encrypted backup frame is truncated.');
            $length = unpack('Nlength', $lengthBytes)['length'];
            $cipher = '';
            while (strlen($cipher) < $length && ! feof($input)) {
                $part = fread($input, $length - strlen($cipher));
                throw_unless(is_string($part), RuntimeException::class, 'Encrypted backup frame could not be read.');
                $cipher .= $part;
            }
            $plain = sodium_crypto_secretstream_xchacha20poly1305_pull($state, $cipher);
            throw_unless(is_array($plain), RuntimeException::class, 'Backup authentication failed.');
            fwrite($output, $plain[0]);
            $final = $plain[1] === SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_FINAL;
        }
        fclose($input);
        fclose($output);
        throw_unless($final, RuntimeException::class, 'Encrypted backup has no authenticated final frame.');
    }

    private function key(): string
    {
        $key = base64_decode((string) config('backup.encryption_key'), true);
        throw_unless(is_string($key) && strlen($key) === SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_KEYBYTES, RuntimeException::class, 'BACKUP_ENCRYPTION_KEY must be a base64-encoded 32-byte key.');

        return $key;
    }

    private function copyOffsite(string $location): bool
    {
        $offsite = config('backup.offsite_disk');
        if (blank($offsite)) {
            return false;
        }
        $stream = $this->disk()->readStream($location);
        throw_unless(is_resource($stream) && Storage::disk((string) $offsite)->writeStream($location, $stream), RuntimeException::class, 'Off-server backup copy failed.');
        fclose($stream);

        return true;
    }

    private function copyFromDisk(BackupRun $run, string $destination): void
    {
        $stream = Storage::disk($run->disk)->readStream($run->location);
        $output = fopen($destination, 'wb');
        throw_unless(is_resource($stream) && is_resource($output), RuntimeException::class, 'Backup file cannot be read.');
        stream_copy_to_stream($stream, $output);
        fclose($stream);
        fclose($output);
    }

    private function disk(): FilesystemAdapter
    {
        return Storage::disk((string) config('backup.disk', 'backups'));
    }

    private function databaseFilename(): string
    {
        return config('database.default') === 'sqlite' ? 'database.sqlite' : 'database.sql';
    }

    private function validateClass(string $class): void
    {
        throw_unless(array_key_exists($class, (array) config('backup.retention_days')), RuntimeException::class, 'Unsupported backup class.');
    }
}
