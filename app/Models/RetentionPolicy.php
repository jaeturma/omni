<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['record_type', 'classification', 'retention_months', 'retention_trigger', 'disposition', 'legal_basis', 'active', 'reviewed_at', 'reviewed_by', 'updated_by'])]
class RetentionPolicy extends Model
{
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    protected function casts(): array
    {
        return ['retention_months' => 'integer', 'active' => 'boolean', 'reviewed_at' => 'date'];
    }
}
