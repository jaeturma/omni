<?php

namespace App\Http\Requests;

use App\Models\FiscalPeriod;
use App\Models\InventoryOpeningBalance;
use App\Models\ProductService;
use App\Support\InventoryWorkflow;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreInventoryOpeningBalanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('create', InventoryOpeningBalance::class);
    }

    public function rules(): array
    {
        return [
            'opening_date' => ['required', 'date'],
            'fiscal_period_id' => ['required', 'integer', 'exists:fiscal_periods,id'],
            'warehouse_id' => ['required', 'integer', 'exists:warehouses,id'],
            'reference' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.product_service_id' => ['required', 'integer', 'distinct', 'exists:product_services,id'],
            'lines.*.quantity' => ['required', 'decimal:0,4', 'gt:0'],
            'lines.*.unit_cost' => ['required', 'decimal:0,4', 'gte:0'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            $period = FiscalPeriod::find($this->integer('fiscal_period_id'));
            if (! $period || $period->status !== 'open' || ! $this->date('opening_date')?->betweenIncluded($period->starts_on, $period->ends_on)) {
                $validator->errors()->add('fiscal_period_id', 'Select the open fiscal period containing the opening date.');
            }

            foreach ($this->array('lines') as $index => $line) {
                $product = ProductService::find($line['product_service_id'] ?? null);
                if ($product && ! InventoryWorkflow::tracks($product)) {
                    $validator->errors()->add("lines.$index.product_service_id", 'Only inventory products may receive opening stock.');
                }
            }
        }];
    }
}
