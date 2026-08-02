<?php

namespace App\Console\Commands;

use App\Models\BackupRun;
use App\Services\BackupManager;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('backup:verify {backupRun : Backup run ID}')]
#[Description('Recalculate a backup hash and validate its encrypted archive')]
class VerifyBackup extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(BackupManager $backups): int
    {
        $run = BackupRun::query()->findOrFail($this->argument('backupRun'));
        $backups->verify($run);
        $this->info("Backup {$run->id} is readable and verified.");

        return self::SUCCESS;
    }
}
