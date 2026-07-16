<?php

namespace App\Http\Requests;

use App\Models\UnitOfMeasure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUnitOfMeasureRequest extends FormRequest
{
    public function authorize(): bool
    {
        $unitOfMeasure = $this->route('unit_of_measure');

        return $unitOfMeasure instanceof UnitOfMeasure && (bool) $this->user()?->can('update', $unitOfMeasure);
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:20', 'regex:/^[A-Z0-9_-]+$/', Rule::unique(UnitOfMeasure::class)->ignore($this->route('unit_of_measure'))],
            'name' => ['required', 'string', 'max:255', Rule::unique(UnitOfMeasure::class)->ignore($this->route('unit_of_measure'))],
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
