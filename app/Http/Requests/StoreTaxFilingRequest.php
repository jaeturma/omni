<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTaxFilingRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $parts = explode(':', (string) $this->input('worksheet_reference'), 2);

        if (count($parts) === 2) {
            $this->merge(['worksheet_type' => $parts[0], 'worksheet_id' => $parts[1]]);
        }
    }

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return (bool) $this->user()?->can($this->boolean('is_amended') ? 'tax-filings.amend' : 'tax-filings.record');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return ['worksheet_reference' => ['required', 'regex:/^(2551q|1701q):[1-9][0-9]*$/'], 'worksheet_type' => ['required', Rule::in(['2551q', '1701q'])], 'worksheet_id' => ['required', 'integer', 'min:1'],
            'filing_channel' => ['required', 'string', 'max:80'], 'filing_date' => ['required', 'date'], 'return_reference' => ['required', 'string', 'max:255', 'unique:tax_filings,return_reference'],
            'is_amended' => ['nullable', 'boolean'], 'original_filing_id' => [Rule::requiredIf($this->boolean('is_amended')), 'nullable', 'integer', 'exists:tax_filings,id'],
            'amendment_reason' => [Rule::requiredIf($this->boolean('is_amended')), 'nullable', 'string', 'max:2000'],
            'amount_declared' => ['required', 'decimal:0,4', 'gte:0'], 'reviewed_by' => ['nullable', 'integer', 'exists:users,id'], 'notes' => ['nullable', 'string', 'max:5000'],
            'confirm_manual_filing' => ['required', 'accepted']];
    }
}
