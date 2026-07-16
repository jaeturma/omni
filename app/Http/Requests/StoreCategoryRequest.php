<?php

namespace App\Http\Requests;

use App\Models\Category;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('create', Category::class);
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:30', 'regex:/^[A-Z0-9_-]+$/', Rule::unique(Category::class)->where('type', $this->string('type')->toString())],
            'name' => ['required', 'string', 'max:255', Rule::unique(Category::class)->where('type', $this->string('type')->toString())],
            'type' => ['required', Rule::in(['product', 'service'])],
            'parent_id' => ['nullable', 'integer', Rule::exists(Category::class, 'id')],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ];
    }

    /** @return array<callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $parent = Category::query()->find($this->integer('parent_id'));
            if ($parent !== null && $parent->type !== $this->string('type')->toString()) {
                $validator->errors()->add('parent_id', 'The parent must have the same category type.');
            }
        }];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'code' => str($this->input('code'))->trim()->upper()->toString(),
            'name' => str($this->input('name'))->squish()->title()->toString(),
            'parent_id' => filled($this->input('parent_id')) ? $this->input('parent_id') : null,
        ]);
    }
}
