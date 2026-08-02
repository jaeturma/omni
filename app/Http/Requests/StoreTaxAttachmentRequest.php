<?php

namespace App\Http\Requests;

use App\Models\TaxFiling;
use App\Models\TaxFilingAttachment;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;

class StoreTaxAttachmentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->route('tax_filing') instanceof TaxFiling && (bool) $this->user()?->can('tax-attachments.upload');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return ['file' => ['required', File::types(['pdf', 'jpg', 'jpeg', 'png'])->max(TaxFilingAttachment::MAX_FILE_SIZE_KB)],
            'attachment_type' => ['required', Rule::in(['proof_of_filing', 'proof_of_payment', 'acknowledgement'])],
            'tax_filing_payment_id' => ['nullable', 'integer', 'exists:tax_filing_payments,id'], 'notes' => ['nullable', 'string', 'max:2000']];
    }
}
