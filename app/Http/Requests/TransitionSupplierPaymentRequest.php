<?php

namespace App\Http\Requests;

use App\Enums\SupplierPaymentStatus;
use App\Models\SupplierPayment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TransitionSupplierPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $payment = $this->route('supplier_payment');
        if (! $payment instanceof SupplierPayment) {
            return false;
        }

        return $this->input('status') === SupplierPaymentStatus::Voided->value ? (bool) $this->user()?->can('void', $payment) : (bool) $this->user()?->can('post', $payment);
    }

    public function rules(): array
    {
        return ['status' => ['required', Rule::in([SupplierPaymentStatus::Posted->value, SupplierPaymentStatus::Voided->value])], 'reason' => [Rule::requiredIf($this->input('status') === SupplierPaymentStatus::Voided->value), 'nullable', 'string', 'max:2000']];
    }
}
