<?php

namespace App\Http\Requests;

use App\Models\PurchaseRequest;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCanvassQuotationRequest extends FormRequest
{
    public function authorize(): bool
    {
        $request = $this->route('purchase_request');

        return $request instanceof PurchaseRequest && (bool) $this->user()?->can('manageCanvass', $request);
    }

    public function rules(): array
    {
        return ['supplier_id' => ['required', Rule::exists('suppliers', 'id')->where('status', 'active')], 'quoted_amount' => ['required', 'decimal:0,4', 'min:0'], 'quotation_date' => ['required', 'date'], 'validity_date' => ['nullable', 'date', 'after_or_equal:quotation_date'], 'delivery_terms' => ['nullable', 'string', 'max:5000'], 'payment_terms' => ['nullable', 'string', 'max:5000'], 'selected' => ['sometimes', 'boolean'], 'evaluation_notes' => ['nullable', 'string', 'max:5000']];
    }
}
