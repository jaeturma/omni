<?php

namespace App\Http\Requests;

use App\Enums\CustomerPaymentStatus;
use App\Models\CustomerPayment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreCustomerPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $payment = $this->route('customer_payment');

        return $payment instanceof CustomerPayment
            ? (bool) $this->user()?->can('update', $payment)
            : (bool) $this->user()?->can('create', CustomerPayment::class);
    }

    public function rules(): array
    {
        return [
            'customer_id' => ['required', Rule::exists('customers', 'id')->where('status', 'active')],
            'payment_method_id' => ['required', Rule::exists('payment_methods', 'id')->where('status', 'active')],
            'bank_id' => ['nullable', Rule::exists('banks', 'id')->where('status', 'active')],
            'payment_date' => ['required', 'date'], 'reference_number' => ['nullable', 'string', 'max:255'],
            'gross_settlement_amount' => ['required', 'decimal:0,4', 'gt:0'],
            'withholding_amount' => ['nullable', 'decimal:0,4', 'gte:0'],
            'other_deductions' => ['nullable', 'decimal:0,4', 'gte:0'],
            'net_cash_received' => ['required', 'decimal:0,4', 'gte:0'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            $payment = $this->route('customer_payment');
            if ($payment instanceof CustomerPayment && $payment->status !== CustomerPaymentStatus::Draft) {
                $validator->errors()->add('status', 'Posted payments cannot be edited.');
            }
            $parts = bcadd((string) ($this->input('withholding_amount') ?: '0'), (string) ($this->input('other_deductions') ?: '0'), 4);
            $parts = bcadd($parts, (string) ($this->input('net_cash_received') ?: '0'), 4);
            if (bccomp($parts, (string) ($this->input('gross_settlement_amount') ?: '0'), 4) !== 0) {
                $validator->errors()->add('gross_settlement_amount', 'Gross settlement must equal cash received plus withholding and other deductions.');
            }
        }];
    }
}
