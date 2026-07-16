<?php

namespace App\Http\Requests;

use App\Models\Supplier;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSupplierRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('create', Supplier::class);
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:30', 'regex:/^[A-Z0-9_-]+$/', Rule::unique(Supplier::class)],
            'name' => ['required', 'string', 'max:255'],
            'tin' => ['nullable', 'regex:/^\d{3}-?\d{3}-?\d{3}(?:-?\d{3,5})?$/', Rule::unique(Supplier::class)],
            'address' => ['required', 'string', 'max:2000'], 'contact_person' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'], 'phone' => ['nullable', 'string', 'max:30'],
            'payment_terms' => ['required', 'integer', 'between:0,3650'], 'status' => ['required', Rule::in(['active', 'inactive'])],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['code' => str($this->input('code'))->trim()->upper()->toString(), 'tin' => filled($this->input('tin')) ? str($this->input('tin'))->trim()->toString() : null]);
    }
}
