<?php

namespace App\Http\Requests;

use App\Models\CustomerPayment;
use Illuminate\Foundation\Http\FormRequest;

class AllocateCustomerPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $payment = $this->route('customer_payment');

        return $payment instanceof CustomerPayment && (bool) $this->user()?->can('allocate', $payment);
    }

    public function rules(): array
    {
        return ['allocations' => ['required', 'array', 'min:1'],
            'allocations.*.sales_invoice_id' => ['required', 'integer', 'distinct', 'exists:sales_invoices,id'],
            'allocations.*.amount' => ['required', 'decimal:0,4', 'gt:0']];
    }
}
