<?php

namespace App\Http\Requests;

use App\Enums\SalesInvoiceStatus;
use App\Models\SalesInvoice;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TransitionSalesInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        $invoice = $this->route('sales_invoice');
        if (! $invoice instanceof SalesInvoice) {
            return false;
        }

        return $this->input('status') === SalesInvoiceStatus::Voided->value
            ? (bool) $this->user()?->can('void', $invoice)
            : (bool) $this->user()?->can('post', $invoice);
    }

    public function rules(): array
    {
        return ['status' => ['required', Rule::in([SalesInvoiceStatus::Posted->value, SalesInvoiceStatus::Voided->value])],
            'reason' => [Rule::requiredIf($this->input('status') === SalesInvoiceStatus::Voided->value), 'nullable', 'string', 'max:2000']];
    }
}
