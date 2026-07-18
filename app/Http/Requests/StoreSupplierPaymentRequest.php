<?php

namespace App\Http\Requests;

use App\Enums\SupplierPaymentStatus;
use App\Models\SupplierPayment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreSupplierPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $payment = $this->route('supplier_payment');

        return $payment instanceof SupplierPayment ? (bool) $this->user()?->can('update', $payment) : (bool) $this->user()?->can('create', SupplierPayment::class);
    }

    public function rules(): array
    {
        return ['supplier_id' => ['required', Rule::exists('suppliers', 'id')->where('status', 'active')], 'payment_method_id' => ['required', Rule::exists('payment_methods', 'id')->where('status', 'active')], 'bank_id' => ['nullable', Rule::exists('banks', 'id')->where('status', 'active')], 'payment_date' => ['required', 'date'], 'reference_number' => ['nullable', 'string', 'max:100'], 'gross_settlement_amount' => ['required', 'decimal:0,4', 'gt:0'], 'withholding_amount' => ['nullable', 'decimal:0,4', 'gte:0'], 'other_deductions' => ['nullable', 'decimal:0,4', 'gte:0'], 'net_cash_paid' => ['required', 'decimal:0,4', 'gte:0'], 'notes' => ['nullable', 'string', 'max:5000']];
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            $payment = $this->route('supplier_payment');
            if ($payment instanceof SupplierPayment && $payment->status !== SupplierPaymentStatus::Draft) {
                $validator->errors()->add('status', 'Posted supplier payments cannot be edited.');
            }
            $parts = bcadd((string) ($this->input('withholding_amount') ?: '0'), (string) ($this->input('other_deductions') ?: '0'), 4);
            $parts = bcadd($parts, (string) ($this->input('net_cash_paid') ?: '0'), 4);
            if (bccomp($parts, (string) ($this->input('gross_settlement_amount') ?: '0'), 4) !== 0) {
                $validator->errors()->add('gross_settlement_amount', 'Gross settlement must equal cash paid plus withholding and other deductions.');
            }
        }];
    }
}
