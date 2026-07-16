<?php

namespace App\Http\Requests;

use App\Models\TaxProfile;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateTaxProfileRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $profile = TaxProfile::query()->where('active', true)->first();

        return $profile ? (bool) $this->user()?->can('update', $profile) : (bool) $this->user()?->can('create', TaxProfile::class);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'taxpayer_type' => ['required', 'string', 'max:100'], 'registration_type' => ['required', 'string', 'max:100'],
            'vat_status' => ['required', 'string', 'max:50'], 'income_tax_option' => ['required', 'string', 'max:100'],
            'percentage_tax_registered' => ['required', 'boolean'], 'percentage_tax_rate' => ['nullable', 'decimal:0,6', 'between:0,100'],
            'percentage_tax_effective_from' => ['nullable', 'date'], 'percentage_tax_effective_to' => ['nullable', 'date', 'after_or_equal:percentage_tax_effective_from'],
            'filing_frequency' => ['required', 'string', 'max:50'], 'registration_start_date' => ['required', 'date'], 'first_filing_period' => ['required', 'string', 'max:50'],
            'rdo_code' => ['required', 'regex:/^\d{3,5}$/'], 'tin' => ['required', 'regex:/^\d{3}-?\d{3}-?\d{3}(?:-?\d{3,5})?$/'],
            'branch_code' => ['required', 'regex:/^\d{3,5}$/'], 'registered_books_type' => ['required', 'string', 'max:100'], 'notes' => ['nullable', 'string', 'max:5000'],
            'active' => ['required', 'boolean'], 'forms' => ['nullable', 'array'], 'forms.*' => ['string', 'max:20', 'distinct'],
        ];
    }
}
