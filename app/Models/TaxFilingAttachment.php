<?php

namespace App\Models;

use DomainException;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['tax_filing_id', 'tax_filing_payment_id', 'attachment_type', 'original_filename', 'stored_filename', 'mime_type', 'file_size', 'file_hash', 'notes', 'uploaded_by'])]
class TaxFilingAttachment extends Model
{
    public const MAX_FILE_SIZE_KB = 10240;

    protected static function booted(): void
    {
        static::updating(function (): void {
            throw new DomainException('Tax filing attachments are immutable.');
        });
        static::deleting(function (): void {
            throw new DomainException('Tax filing attachments cannot be deleted.');
        });
    }

    public function taxFiling(): BelongsTo
    {
        return $this->belongsTo(TaxFiling::class);
    }

    public function taxFilingPayment(): BelongsTo
    {
        return $this->belongsTo(TaxFilingPayment::class);
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    protected function casts(): array
    {
        return ['file_size' => 'integer'];
    }
}
