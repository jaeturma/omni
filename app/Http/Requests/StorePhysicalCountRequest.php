<?php

namespace App\Http\Requests;

use App\Models\FiscalPeriod;
use App\Models\PhysicalCount;
use App\Models\ProductService;
use App\Support\InventoryWorkflow;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StorePhysicalCountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('create', PhysicalCount::class);
    }

    public function rules(): array
    {
        return [
            'count_date' => ['required', 'date'],
            'fiscal_period_id' => ['required', 'integer', 'exists:fiscal_periods,id'],
            'warehouse_id' => ['required', 'integer', Rule::exists('warehouses', 'id')->where('status', 'active')],
            'blind_count' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'product_ids' => ['required', 'array', 'min:1'],
            'product_ids.*' => ['required', 'integer', 'distinct', 'exists:product_services,id'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            $period = FiscalPeriod::find($this->integer('fiscal_period_id'));
            if (! $period || $period->status !== 'open' || ! $this->date('count_date')?->betweenIncluded($period->starts_on, $period->ends_on)) {
                $validator->errors()->add('fiscal_period_id', 'Select the open fiscal period containing the count date.');
            }
            foreach ($this->array('product_ids') as $index => $productId) {
                $product = ProductService::find($productId);
                if ($product && ! InventoryWorkflow::tracks($product)) {
                    $validator->errors()->add("product_ids.$index", 'Only inventory products may be counted.');
                }
            }
        }];
    }
}
