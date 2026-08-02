<?php

namespace App\Console\Commands;

use App\Services\BackupManager;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('backup:run {--class=daily : daily, weekly, monthly, pre_deployment, or pre_migration}')]
#[Description('Create, encrypt, copy, and verify a database and private-file backup')]
class RunBackup extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(BackupManager $backups): int
    {
        $run = $backups->create((string) $this->option('class'));
        $this->info("Backup {$run->id} verified ({$run->size_bytes} bytes, SHA-256 {$run->sha256}).");

        return self::SUCCESS;
    }
}
