<?php

namespace App\Http\Requests;

use App\Enums\InventoryMovementType;
use App\Models\Brand;
use App\Models\Category;
use App\Models\ProductService;
use App\Models\Warehouse;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class InventoryReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('inventory-reports.view');
    }

    public function rules(): array
    {
        return [
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'as_of' => ['required', 'date'],
            'product_service_id' => ['nullable', 'integer', Rule::exists(ProductService::class, 'id')],
            'category_id' => ['nullable', 'integer', Rule::exists(Category::class, 'id')],
            'brand_id' => ['nullable', 'integer', Rule::exists(Brand::class, 'id')],
            'warehouse_id' => ['nullable', 'integer', Rule::exists(Warehouse::class, 'id')],
            'movement_type' => ['nullable', Rule::enum(InventoryMovementType::class)],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($this->date('end_date')?->isAfter($this->date('as_of'))) {
                $validator->errors()->add('as_of', 'The as-of date must be on or after the range end date.');
            }
        }];
    }

    protected function prepareForValidation(): void
    {
        $asOf = $this->input('as_of', now()->toDateString());
        $this->merge([
            'as_of' => $asOf,
            'start_date' => $this->input('start_date', now()->subDays(30)->toDateString()),
            'end_date' => $this->input('end_date', $asOf),
            'product_service_id' => $this->filled('product_service_id') ? $this->input('product_service_id') : null,
            'category_id' => $this->filled('category_id') ? $this->input('category_id') : null,
            'brand_id' => $this->filled('brand_id') ? $this->input('brand_id') : null,
            'warehouse_id' => $this->filled('warehouse_id') ? $this->input('warehouse_id') : null,
            'movement_type' => $this->filled('movement_type') ? $this->input('movement_type') : null,
        ]);
    }
}
