<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['fiscal_period_id', 'action', 'from_status', 'to_status', 'notes', 'checklist', 'overrides', 'performed_by', 'performed_at'])]
class FiscalPeriodEvent extends Model
{
    public function fiscalPeriod(): BelongsTo
    {
        return $this->belongsTo(FiscalPeriod::class);
    }

    public function performedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
    }

    protected function casts(): array
    {
        return ['checklist' => 'array', 'overrides' => 'array', 'performed_at' => 'datetime'];
    }
}
