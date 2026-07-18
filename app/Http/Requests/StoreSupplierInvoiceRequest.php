<?php

namespace App\Http\Requests;

use App\Models\SupplierInvoice;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSupplierInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', SupplierInvoice::class);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return $this->invoiceRules();
    }

    /** @return array<string, mixed> */
    protected function invoiceRules(): array
    {
        return ['supplier_id' => ['required', 'integer', 'exists:suppliers,id'], 'purchase_order_id' => ['nullable', 'integer', 'exists:purchase_orders,id'], 'receiving_record_id' => ['nullable', 'integer', 'exists:receiving_records,id'], 'fiscal_period_id' => ['required', 'integer', 'exists:fiscal_periods,id'], 'supplier_invoice_number' => ['required', 'string', 'max:100', Rule::unique('supplier_invoices')->where(fn ($query) => $query->where('supplier_id', $this->integer('supplier_id')))], 'invoice_date' => ['required', 'date'], 'due_date' => ['required', 'date', 'after_or_equal:invoice_date'], 'freight_amount' => ['nullable', 'decimal:0,4', 'min:0'], 'other_charges_amount' => ['nullable', 'decimal:0,4', 'min:0'], 'withholding_expected_amount' => ['nullable', 'decimal:0,4', 'min:0'], 'notes' => ['nullable', 'string'], 'lines' => ['required', 'array', 'min:1'], 'lines.*.purchase_order_line_id' => ['nullable', 'integer', 'exists:purchase_order_lines,id'], 'lines.*.receiving_record_line_id' => ['nullable', 'integer', 'exists:receiving_record_lines,id'], 'lines.*.item_type' => ['required', Rule::in(['product', 'service', 'expense'])], 'lines.*.sku' => ['nullable', 'string', 'max:50'], 'lines.*.description' => ['required', 'string', 'max:255'], 'lines.*.uom_code' => ['required', 'string', 'max:20'], 'lines.*.uom_name' => ['required', 'string', 'max:255'], 'lines.*.quantity' => ['required', 'decimal:0,4', 'min:0'], 'lines.*.unit_cost' => ['required', 'decimal:0,4', 'min:0'], 'lines.*.discount_rate' => ['nullable', 'decimal:0,6', 'between:0,100'], 'lines.*.notes' => ['nullable', 'string']];
    }
}
