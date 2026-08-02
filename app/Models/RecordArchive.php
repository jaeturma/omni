<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['subject_type', 'subject_id', 'archived_at', 'archived_by', 'reason'])]
class RecordArchive extends Model
{
    public function archiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'archived_by');
    }

    protected function casts(): array
    {
        return ['archived_at' => 'datetime'];
    }
}
