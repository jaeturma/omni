<?php

namespace App\Http\Requests;

use App\Models\PaymentMethod;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePaymentMethodRequest extends FormRequest
{
    public function authorize(): bool
    {
        $paymentMethod = $this->route('payment_method');

        return $paymentMethod instanceof PaymentMethod && (bool) $this->user()?->can('update', $paymentMethod);
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return ['code' => ['required', 'string', 'max:30', 'regex:/^[A-Z0-9_-]+$/', Rule::unique(PaymentMethod::class)->ignore($this->route('payment_method'))], 'name' => ['required', 'string', 'max:255', Rule::unique(PaymentMethod::class)->ignore($this->route('payment_method'))], 'type' => ['required', Rule::in(['cash', 'bank', 'gcash', 'cheque', 'online_transfer'])], 'status' => ['required', Rule::in(['active', 'inactive'])]];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['code' => str($this->input('code'))->trim()->upper()->toString(), 'name' => str($this->input('name'))->squish()->title()->toString()]);
    }
}
