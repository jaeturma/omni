<?php

namespace App\Console\Commands;

use App\Models\BackupRun;
use App\Services\BackupManager;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('backup:restore-test {backupRun : Verified backup run ID} {exercise : Unique exercise identifier} {--database= : Empty isolated MySQL database name}')]
#[Description('Restore and smoke-test a verified backup in an isolated exercise location')]
class RestoreBackupExercise extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(BackupManager $backups): int
    {
        if (app()->isProduction() && ! $this->confirm('This restores only to the configured isolated test destination. Continue?')) {
            return self::FAILURE;
        }
        $run = BackupRun::query()->findOrFail($this->argument('backupRun'));
        $location = $backups->restoreExercise($run, (string) $this->argument('exercise'), $this->option('database') ?: null);
        $this->info("Restore exercise passed at {$location}. Remove the exercise data after review.");

        return self::SUCCESS;
    }
}
