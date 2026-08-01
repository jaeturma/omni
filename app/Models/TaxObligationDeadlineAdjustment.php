<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaxObligationDeadlineAdjustment extends Model
{
    protected $fillable = ['tax_obligation_id', 'previous_due_date', 'adjusted_due_date', 'reason', 'source_title', 'source_url', 'adjusted_by'];

    /** @return BelongsTo<TaxObligation, $this> */
    public function taxObligation(): BelongsTo
    {
        return $this->belongsTo(TaxObligation::class);
    }

    /** @return BelongsTo<User, $this> */
    public function adjustedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'adjusted_by');
    }

    protected function casts(): array
    {
        return ['previous_due_date' => 'date', 'adjusted_due_date' => 'date'];
    }
}
