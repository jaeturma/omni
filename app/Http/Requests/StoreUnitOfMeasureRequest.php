<?php

namespace App\Http\Requests;

use App\Models\UnitOfMeasure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUnitOfMeasureRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('create', UnitOfMeasure::class);
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:20', 'regex:/^[A-Z0-9_-]+$/', Rule::unique(UnitOfMeasure::class)],
            'name' => ['required', 'string', 'max:255', Rule::unique(UnitOfMeasure::class)],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'code' => str($this->input('code'))->trim()->upper()->toString(),
            'name' => str($this->input('name'))->squish()->title()->toString(),
        ]);
    }
}
