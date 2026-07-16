<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['document_sequence_id', 'fiscal_year_id', 'number', 'document_number', 'issued_at', 'issued_by'])]
class DocumentNumberReservation extends Model
{
    public function documentSequence(): BelongsTo
    {
        return $this->belongsTo(DocumentSequence::class);
    }

    public function fiscalYear(): BelongsTo
    {
        return $this->belongsTo(FiscalYear::class);
    }

    public function issuer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    protected function casts(): array
    {
        return ['number' => 'integer', 'issued_at' => 'datetime'];
    }
}
