<?php

namespace App\Http\Requests;

use App\Models\ReceivingRecord;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreReceivingRecordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('create', ReceivingRecord::class);
    }

    public function rules(): array
    {
        return [
            'purchase_order_id' => ['required', Rule::exists('purchase_orders', 'id')],
            'warehouse_id' => ['nullable', Rule::exists('warehouses', 'id')->where('status', 'active')],
            'receiving_date' => ['required', 'date'], 'delivery_location' => ['required', 'string', 'max:5000'],
            'delivery_receipt_number' => ['nullable', 'string', 'max:255'], 'supplier_invoice_reference' => ['nullable', 'string', 'max:255'], 'inspection_reference' => ['nullable', 'string', 'max:255'],
            'received_by' => ['required', Rule::exists('users', 'id')->where('active', true)], 'inspected_by' => ['nullable', Rule::exists('users', 'id')->where('active', true)], 'accepted_by' => ['nullable', Rule::exists('users', 'id')->where('active', true)],
            'notes' => ['nullable', 'string', 'max:10000'], 'lines' => ['required', 'array', 'min:1', 'max:100'],
            'lines.*.purchase_order_line_id' => ['required', 'distinct', Rule::exists('purchase_order_lines', 'id')],
            'lines.*.received_quantity' => ['required', 'decimal:0,4', 'min:0'], 'lines.*.accepted_quantity' => ['required', 'decimal:0,4', 'min:0'], 'lines.*.rejected_quantity' => ['required', 'decimal:0,4', 'min:0'],
            'lines.*.rejection_reason' => ['nullable', 'string', 'max:2000'], 'lines.*.notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
