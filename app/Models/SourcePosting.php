<?php

namespace App\Models;

use App\Enums\AccountingSourceType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['source_type', 'source_id', 'journal_entry_id', 'status', 'attempt_count', 'last_attempt_at', 'posted_at', 'failure_reason', 'last_attempted_by'])]
class SourcePosting extends Model
{
    protected $attributes = ['status' => 'pending', 'attempt_count' => 0];

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }

    protected function casts(): array
    {
        return [
            'source_type' => AccountingSourceType::class,
            'attempt_count' => 'integer',
            'last_attempt_at' => 'datetime',
            'posted_at' => 'datetime',
        ];
    }
}
