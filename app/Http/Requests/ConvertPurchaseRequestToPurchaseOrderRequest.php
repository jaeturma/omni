<?php

namespace App\Http\Requests;

use App\Models\PurchaseOrder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ConvertPurchaseRequestToPurchaseOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('create', PurchaseOrder::class);
    }

    public function rules(): array
    {
        return ['supplier_id' => ['nullable', Rule::exists('suppliers', 'id')->where('status', 'active')], 'order_date' => ['required', 'date'], 'expected_delivery_date' => ['nullable', 'date', 'after_or_equal:order_date'], 'delivery_location' => ['required', 'string', 'max:5000'], 'supplier_quotation_reference' => ['nullable', 'string', 'max:255'], 'payment_terms' => ['nullable', 'string', 'max:255'], 'notes' => ['nullable', 'string', 'max:10000'], 'document_discount_rate' => ['required', 'decimal:0,6', 'between:0,100'], 'freight' => ['required', 'decimal:0,4', 'min:0'], 'other_charges' => ['required', 'decimal:0,4', 'min:0']];
    }
}
