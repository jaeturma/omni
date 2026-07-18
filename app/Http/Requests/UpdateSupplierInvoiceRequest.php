<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class UpdateSupplierInvoiceRequest extends StoreSupplierInvoiceRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('supplier_invoice'));
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $rules = $this->invoiceRules();
        $rules['supplier_invoice_number'] = ['required', 'string', 'max:100', Rule::unique('supplier_invoices')->where(fn ($query) => $query->where('supplier_id', $this->integer('supplier_id')))->ignore($this->route('supplier_invoice'))];

        return $rules;
    }
}
