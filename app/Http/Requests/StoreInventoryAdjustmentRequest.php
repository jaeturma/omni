<?php

namespace App\Http\Requests;

use App\Models\FiscalPeriod;
use App\Models\InventoryAdjustment;
use App\Models\ProductService;
use App\Support\InventoryWorkflow;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreInventoryAdjustmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('create', InventoryAdjustment::class);
    }

    public function rules(): array
    {
        return [
            'adjustment_date' => ['required', 'date'],
            'fiscal_period_id' => ['required', 'integer', 'exists:fiscal_periods,id'],
            'warehouse_id' => ['required', 'integer', 'exists:warehouses,id'],
            'type' => ['required', Rule::in(['in', 'out'])],
            'inventory_adjustment_reason_id' => ['required', 'integer', Rule::exists('inventory_adjustment_reasons', 'id')->where('active', true)],
            'explanation' => ['required', 'string', 'max:5000'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.product_service_id' => ['required', 'integer', 'distinct', 'exists:product_services,id'],
            'lines.*.quantity' => ['required', 'decimal:0,4', 'gt:0'],
            'lines.*.unit_cost' => [Rule::requiredIf($this->input('type') === 'in'), 'nullable', 'decimal:0,4', 'gte:0'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            $period = FiscalPeriod::find($this->integer('fiscal_period_id'));
            if (! $period || $period->status !== 'open' || ! $this->date('adjustment_date')?->betweenIncluded($period->starts_on, $period->ends_on)) {
                $validator->errors()->add('fiscal_period_id', 'Select the open fiscal period containing the adjustment date.');
            }
            foreach ($this->array('lines') as $index => $line) {
                $product = ProductService::find($line['product_service_id'] ?? null);
                if ($product && ! InventoryWorkflow::tracks($product)) {
                    $validator->errors()->add("lines.$index.product_service_id", 'Only inventory products may be adjusted.');
                }
            }
        }];
    }
}
