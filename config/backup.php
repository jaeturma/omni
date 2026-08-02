<?php

return [
    'disk' => env('BACKUP_DISK', 'backups'),
    'offsite_disk' => env('BACKUP_OFFSITE_DISK'),
    'encryption_key' => env('BACKUP_ENCRYPTION_KEY'),
    'mysqldump_binary' => env('MYSQLDUMP_BINARY', 'mysqldump'),
    'mysql_binary' => env('MYSQL_BINARY', 'mysql'),
    'retention_days' => ['daily' => 14, 'weekly' => 56, 'monthly' => 395, 'pre_deployment' => 30, 'pre_migration' => 30],
    'restore_root' => storage_path('app/restore-tests'),
];
