<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['attachable_type', 'attachable_id', 'document_type', 'original_filename', 'stored_filename', 'mime_type', 'file_size', 'file_hash', 'document_date', 'reference_number', 'notes', 'uploaded_by', 'deleted_by', 'deletion_reason'])]
class PurchasingAttachment extends Model
{
    use SoftDeletes;

    public const MAX_FILE_SIZE_KB = 10240;

    public const DOCUMENT_TYPES = ['purchase_request', 'supplier_quotation', 'abstract_of_canvass', 'purchase_order', 'delivery_receipt', 'inspection_acceptance_report', 'supplier_invoice', 'official_receipt', 'deposit_or_transfer_confirmation', 'withholding_certificate', 'expense_receipt', 'other_supporting_document'];

    public function attachable(): MorphTo
    {
        return $this->morphTo();
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    protected function casts(): array
    {
        return ['document_date' => 'date', 'file_size' => 'integer', 'deleted_at' => 'datetime'];
    }
}
