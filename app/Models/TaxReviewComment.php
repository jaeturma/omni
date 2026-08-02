<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['tax_period_id', 'comment', 'status', 'created_by', 'resolved_at', 'resolved_by'])]
class TaxReviewComment extends Model
{
    public function taxPeriod(): BelongsTo
    {
        return $this->belongsTo(TaxPeriod::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    protected function casts(): array
    {
        return ['resolved_at' => 'datetime'];
    }
}
