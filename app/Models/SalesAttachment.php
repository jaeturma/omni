<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['attachable_type', 'attachable_id', 'document_type', 'original_filename', 'stored_filename', 'mime_type', 'file_size', 'file_hash', 'document_date', 'reference_number', 'notes', 'uploaded_by', 'deleted_by', 'deletion_reason'])]
class SalesAttachment extends Model
{
    use SoftDeletes;

    public const MAX_FILE_SIZE_KB = 10240;

    public const ALLOWED_MIME_TYPES = ['application/pdf', 'image/jpeg', 'image/png', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'];

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
