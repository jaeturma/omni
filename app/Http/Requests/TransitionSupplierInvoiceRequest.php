<?php

namespace App\Http\Requests;

use App\Enums\SupplierInvoiceStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TransitionSupplierInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        $invoice = $this->route('supplier_invoice');

        return match ($this->input('status')) {
            'posted' => $this->user()->can('post', $invoice), 'voided' => $this->user()->can('void', $invoice), default => false
        };
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['status' => ['required', Rule::enum(SupplierInvoiceStatus::class)->only([SupplierInvoiceStatus::Posted, SupplierInvoiceStatus::Voided])], 'reason' => [Rule::requiredIf($this->input('status') === 'voided'), 'nullable', 'string', 'max:2000']];
    }
}
