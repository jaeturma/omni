<?php

namespace App\Http\Requests;

use App\Models\PaymentMethod;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePaymentMethodRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('create', PaymentMethod::class);
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return ['code' => ['required', 'string', 'max:30', 'regex:/^[A-Z0-9_-]+$/', Rule::unique(PaymentMethod::class)], 'name' => ['required', 'string', 'max:255', Rule::unique(PaymentMethod::class)], 'type' => ['required', Rule::in(['cash', 'bank', 'gcash', 'cheque', 'online_transfer'])], 'status' => ['required', Rule::in(['active', 'inactive'])]];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['code' => str($this->input('code'))->trim()->upper()->toString(), 'name' => str($this->input('name'))->squish()->title()->toString()]);
    }
}
