<?php

namespace App\Http\Requests;

use App\Models\GovernmentDeduction;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ApplyWithholdingCertificateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $certificate = $this->route('government_deduction');

        return $certificate instanceof GovernmentDeduction && (bool) $this->user()?->can('apply', $certificate);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return ['tax_obligation_id' => ['required', 'integer', 'exists:tax_obligations,id'], 'amount' => ['required', 'decimal:0,4', 'gt:0'],
            'evidence_reference' => ['required', 'string', 'max:255'], 'notes' => ['nullable', 'string', 'max:2000']];
    }
}
