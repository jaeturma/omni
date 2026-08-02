<?php

namespace App\Http\Requests;

use App\Models\TaxFiling;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreTaxPaymentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->route('tax_filing') instanceof TaxFiling && (bool) $this->user()?->can('tax-payments.record');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return ['payment_channel' => ['required', 'string', 'max:80'], 'payment_date' => ['required', 'date'],
            'payment_reference' => ['required', 'string', 'max:255', 'unique:tax_filing_payments,payment_reference'],
            'amount_paid' => ['required', 'decimal:0,4', 'gt:0'], 'bank_or_provider' => ['nullable', 'string', 'max:255'], 'notes' => ['nullable', 'string', 'max:2000']];
    }
}
