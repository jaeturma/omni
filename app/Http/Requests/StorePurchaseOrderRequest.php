<?php

namespace App\Http\Requests;

use App\Models\ProductService;
use App\Models\PurchaseOrder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePurchaseOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('create', PurchaseOrder::class);
    }

    public function rules(): array
    {
        return [
            'supplier_id' => ['required', Rule::exists('suppliers', 'id')->where('status', 'active')], 'order_date' => ['required', 'date'], 'expected_delivery_date' => ['nullable', 'date', 'after_or_equal:order_date'],
            'delivery_location' => ['required', 'string', 'max:5000'], 'supplier_quotation_reference' => ['nullable', 'string', 'max:255'], 'payment_terms' => ['nullable', 'string', 'max:255'], 'notes' => ['nullable', 'string', 'max:10000'],
            'document_discount_rate' => ['required', 'decimal:0,6', 'between:0,100'], 'freight' => ['required', 'decimal:0,4', 'min:0'], 'other_charges' => ['required', 'decimal:0,4', 'min:0'],
            'lines' => ['required', 'array', 'min:1', 'max:100'], 'lines.*.product_service_id' => ['nullable', Rule::exists((new ProductService)->getTable(), 'id')->where('status', 'active')],
            'lines.*.description' => ['required', 'string', 'max:1000'], 'lines.*.uom_code' => ['required_without:lines.*.product_service_id', 'nullable', 'string', 'max:20'], 'lines.*.uom_name' => ['required_without:lines.*.product_service_id', 'nullable', 'string', 'max:255'],
            'lines.*.ordered_quantity' => ['required', 'decimal:0,4', 'gt:0'], 'lines.*.unit_cost' => ['required', 'decimal:0,4', 'min:0'], 'lines.*.discount_rate' => ['required', 'decimal:0,6', 'between:0,100'],
        ];
    }
}
