<?php

namespace App\Http\Requests;

use App\Models\ProductService;
use App\Models\SalesOrder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSalesOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('create', SalesOrder::class);
    }

    public function rules(): array
    {
        return ['customer_id' => ['required', Rule::exists('customers', 'id')->where('status', 'active')], 'order_date' => ['required', 'date'], 'promised_delivery_date' => ['nullable', 'date', 'after_or_equal:order_date'], 'customer_po_number' => ['nullable', 'string', 'max:255'], 'payment_terms' => ['required', 'integer', 'between:0,3650'], 'billing_address' => ['required', 'string', 'max:5000'], 'delivery_address' => ['required', 'string', 'max:5000'], 'notes' => ['nullable', 'string', 'max:10000'], 'document_discount_rate' => ['required', 'decimal:0,6', 'between:0,100'], 'lines' => ['required', 'array', 'min:1', 'max:100'], 'lines.*.product_service_id' => ['required', 'distinct', Rule::exists((new ProductService)->getTable(), 'id')->where('status', 'active')], 'lines.*.description' => ['required', 'string', 'max:1000'], 'lines.*.ordered_quantity' => ['required', 'decimal:0,4', 'gt:0'], 'lines.*.unit_price' => ['required', 'decimal:0,4', 'min:0'], 'lines.*.discount_rate' => ['required', 'decimal:0,6', 'between:0,100']];
    }
}
