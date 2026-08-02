<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** @property numeric-string $amount */
#[Fillable(['government_deduction_id', 'tax_obligation_id', 'amount', 'evidence_reference', 'notes', 'applied_by', 'applied_at'])]
class WithholdingCertificateApplication extends Model
{
    public function certificate(): BelongsTo
    {
        return $this->belongsTo(GovernmentDeduction::class, 'government_deduction_id');
    }

    public function taxObligation(): BelongsTo
    {
        return $this->belongsTo(TaxObligation::class);
    }

    protected function casts(): array
    {
        return ['amount' => 'decimal:4', 'applied_at' => 'datetime'];
    }
}
