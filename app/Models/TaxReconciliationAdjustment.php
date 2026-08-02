<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $reviewer_id
 * @property string $status
 * @property string $evidence_reference
 * @property numeric-string $amount
 * @property CarbonInterface|null $reviewed_at
 */
class TaxReconciliationAdjustment extends Model
{
    protected $fillable = ['amount', 'reason', 'evidence_reference', 'reviewer_id', 'status', 'review_notes', 'reviewed_at', 'reviewed_by', 'created_by'];

    protected $attributes = ['status' => 'pending'];

    /** @return BelongsTo<TaxReconciliation, $this> */
    public function reconciliation(): BelongsTo
    {
        return $this->belongsTo(TaxReconciliation::class, 'tax_reconciliation_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    protected function casts(): array
    {
        return ['amount' => 'decimal:4', 'reviewed_at' => 'datetime'];
    }
}
