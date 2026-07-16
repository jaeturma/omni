<?php

namespace App\Http\Requests;

use App\Enums\CustomerPaymentStatus;
use App\Models\CustomerPayment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TransitionCustomerPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $payment = $this->route('customer_payment');
        if (! $payment instanceof CustomerPayment) {
            return false;
        }

        return $this->input('status') === CustomerPaymentStatus::Voided->value
            ? (bool) $this->user()?->can('void', $payment)
            : (bool) $this->user()?->can('post', $payment);
    }

    public function rules(): array
    {
        return ['status' => ['required', Rule::in([CustomerPaymentStatus::Posted->value, CustomerPaymentStatus::Voided->value])],
            'reason' => [Rule::requiredIf($this->input('status') === CustomerPaymentStatus::Voided->value), 'nullable', 'string', 'max:2000']];
    }
}
