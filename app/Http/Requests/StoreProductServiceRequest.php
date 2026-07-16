<?php

namespace App\Http\Requests;

use App\Models\Category;
use App\Models\ProductService;
use App\Models\UnitOfMeasure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreProductServiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('create', ProductService::class);
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'sku' => ['required', 'string', 'max:50', 'regex:/^[A-Z0-9_-]+$/', Rule::unique(ProductService::class)],
            'barcode' => ['nullable', 'string', 'max:100', Rule::unique(ProductService::class)],
            'name' => ['required', 'string', 'max:255'], 'description' => ['nullable', 'string', 'max:5000'],
            'type' => ['required', Rule::in(['product', 'service'])],
            'category_id' => ['required', 'integer', Rule::exists(Category::class, 'id')],
            'unit_of_measure_id' => ['required', 'integer', Rule::exists(UnitOfMeasure::class, 'id')],
            'default_cost' => ['required', 'numeric', 'decimal:0,4', 'min:0'],
            'selling_price' => ['required', 'numeric', 'decimal:0,4', 'min:0'],
            'reorder_level' => ['required', 'numeric', 'decimal:0,4', 'min:0'],
            'is_inventory' => ['required', 'boolean'], 'status' => ['required', Rule::in(['active', 'inactive'])],
        ];
    }

    /** @return array<callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $category = Category::query()->find($this->integer('category_id'));
            if ($category !== null && $category->type !== $this->string('type')->toString()) {
                $validator->errors()->add('category_id', 'The category must match the catalog item type.');
            }
            if ($this->string('type')->toString() === 'service' && $this->boolean('is_inventory')) {
                $validator->errors()->add('is_inventory', 'Services cannot be inventory items.');
            }
            if (! $this->boolean('is_inventory') && ! preg_match('/^0+(?:\.0+)?$/', $this->string('reorder_level')->toString())) {
                $validator->errors()->add('reorder_level', 'Reorder level must be zero when inventory tracking is disabled.');
            }
        }];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'sku' => str($this->input('sku'))->trim()->upper()->toString(),
            'barcode' => filled($this->input('barcode')) ? str($this->input('barcode'))->trim()->toString() : null,
            'name' => str($this->input('name'))->squish()->toString(),
            'description' => filled($this->input('description')) ? str($this->input('description'))->trim()->toString() : null,
        ]);
    }
}
