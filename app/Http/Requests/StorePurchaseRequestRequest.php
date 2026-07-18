<?php

namespace App\Http\Requests;

use App\Models\ProductService;
use App\Models\PurchaseRequest;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePurchaseRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('create', PurchaseRequest::class);
    }

    public function rules(): array
    {
        return [
            'request_date' => ['required', 'date'], 'requested_by' => ['required', Rule::exists('users', 'id')->where('active', true)],
            'needed_by' => ['nullable', 'date', 'after_or_equal:request_date'], 'purpose' => ['required', 'string', 'max:5000'],
            'requesting_unit' => ['nullable', 'string', 'max:255'], 'project_reference' => ['nullable', 'string', 'max:255'], 'notes' => ['nullable', 'string', 'max:10000'],
            'lines' => ['required', 'array', 'min:1', 'max:100'],
            'lines.*.product_service_id' => ['nullable', Rule::exists((new ProductService)->getTable(), 'id')->where('status', 'active')],
            'lines.*.preferred_supplier_id' => ['nullable', Rule::exists('suppliers', 'id')->where('status', 'active')],
            'lines.*.description' => ['required', 'string', 'max:1000'], 'lines.*.uom_code' => ['required_without:lines.*.product_service_id', 'nullable', 'string', 'max:20'],
            'lines.*.uom_name' => ['required_without:lines.*.product_service_id', 'nullable', 'string', 'max:255'],
            'lines.*.quantity' => ['required', 'decimal:0,4', 'gt:0'], 'lines.*.estimated_unit_cost' => ['required', 'decimal:0,4', 'min:0'],
            'lines.*.notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
