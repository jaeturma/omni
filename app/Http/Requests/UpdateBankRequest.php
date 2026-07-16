<?php

namespace App\Http\Requests;

use App\Models\Bank;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateBankRequest extends FormRequest
{
    public function authorize(): bool
    {
        $bank = $this->route('bank');

        return $bank instanceof Bank && (bool) $this->user()?->can('update', $bank);
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return ['code' => ['required', 'string', 'max:30', 'regex:/^[A-Z0-9_-]+$/', Rule::unique(Bank::class)->ignore($this->route('bank'))], 'name' => ['required', 'string', 'max:255', Rule::unique(Bank::class)->ignore($this->route('bank'))], 'swift_code' => ['nullable', 'string', 'regex:/^[A-Z0-9]{8}(?:[A-Z0-9]{3})?$/', Rule::unique(Bank::class)->ignore($this->route('bank'))], 'status' => ['required', Rule::in(['active', 'inactive'])]];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['code' => str($this->input('code'))->trim()->upper()->toString(), 'name' => str($this->input('name'))->squish()->title()->toString(), 'swift_code' => filled($this->input('swift_code')) ? str($this->input('swift_code'))->trim()->upper()->toString() : null]);
    }
}
