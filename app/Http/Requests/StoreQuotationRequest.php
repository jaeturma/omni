<?php

namespace App\Http\Requests;

use App\Models\ProductService;
use App\Models\Quotation;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreQuotationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('create', Quotation::class);
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'customer_id' => ['required', Rule::exists('customers', 'id')->where('status', 'active')],
            'quotation_date' => ['required', 'date'], 'valid_until' => ['required', 'date', 'after_or_equal:quotation_date'],
            'contact_name' => ['nullable', 'string', 'max:255'], 'contact_email' => ['nullable', 'email', 'max:255'], 'contact_phone' => ['nullable', 'string', 'max:50'],
            'billing_address' => ['required', 'string', 'max:5000'], 'delivery_address' => ['required', 'string', 'max:5000'],
            'reference' => ['nullable', 'string', 'max:255'], 'notes' => ['nullable', 'string', 'max:10000'], 'terms_and_conditions' => ['nullable', 'string', 'max:10000'],
            'document_discount_rate' => ['required', 'decimal:0,6', 'between:0,100'],
            'lines' => ['required', 'array', 'min:1', 'max:100'],
            'lines.*.product_service_id' => ['required', 'distinct', Rule::exists((new ProductService)->getTable(), 'id')->where('status', 'active')],
            'lines.*.description' => ['required', 'string', 'max:1000'],
            'lines.*.quantity' => ['required', 'decimal:0,4', 'gt:0'], 'lines.*.unit_price' => ['required', 'decimal:0,4', 'min:0'],
            'lines.*.discount_rate' => ['required', 'decimal:0,6', 'between:0,100'],
        ];
    }
}
