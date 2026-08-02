<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['backup_class', 'status', 'disk', 'location', 'size_bytes', 'sha256', 'encrypted', 'offsite_copied', 'started_at', 'completed_at', 'verified_at', 'restore_tested_at', 'failure_reason', 'initiated_by'])]
class BackupRun extends Model
{
    public function initiator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'initiated_by');
    }

    protected function casts(): array
    {
        return ['size_bytes' => 'integer', 'encrypted' => 'boolean', 'offsite_copied' => 'boolean', 'started_at' => 'datetime', 'completed_at' => 'datetime', 'verified_at' => 'datetime', 'restore_tested_at' => 'datetime'];
    }
}
