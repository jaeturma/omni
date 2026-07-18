<?php

namespace App\Http\Requests;

use App\Models\PurchasingAttachment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;

class StorePurchasingAttachmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', PurchasingAttachment::class) ?? false;
    }

    public function rules(): array
    {
        return ['file' => ['required', File::types(['pdf', 'jpg', 'jpeg', 'png', 'docx', 'xlsx'])->max(PurchasingAttachment::MAX_FILE_SIZE_KB)],
            'document_type' => ['required', Rule::in(PurchasingAttachment::DOCUMENT_TYPES)], 'document_date' => ['required', 'date'],
            'reference_number' => ['nullable', 'string', 'max:100'], 'notes' => ['nullable', 'string', 'max:2000']];
    }
}
