<?php

namespace App\Http\Requests;

use App\Models\FiscalPeriod;
use App\Models\InventoryTransfer;
use App\Models\ProductService;
use App\Support\InventoryWorkflow;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreInventoryTransferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('create', InventoryTransfer::class);
    }

    public function rules(): array
    {
        return [
            'transfer_date' => ['required', 'date'],
            'fiscal_period_id' => ['required', 'integer', 'exists:fiscal_periods,id'],
            'source_warehouse_id' => ['required', 'integer', Rule::exists('warehouses', 'id')->where('status', 'active'), 'different:destination_warehouse_id'],
            'destination_warehouse_id' => ['required', 'integer', Rule::exists('warehouses', 'id')->where('status', 'active')],
            'reference' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.product_service_id' => ['required', 'integer', 'distinct', 'exists:product_services,id'],
            'lines.*.quantity' => ['required', 'decimal:0,4', 'gt:0'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            $period = FiscalPeriod::find($this->integer('fiscal_period_id'));
            if (! $period || $period->status !== 'open' || ! $this->date('transfer_date')?->betweenIncluded($period->starts_on, $period->ends_on)) {
                $validator->errors()->add('fiscal_period_id', 'Select the open fiscal period containing the transfer date.');
            }
            foreach ($this->array('lines') as $index => $line) {
                $product = ProductService::find($line['product_service_id'] ?? null);
                if ($product && ! InventoryWorkflow::tracks($product)) {
                    $validator->errors()->add("lines.$index.product_service_id", 'Only inventory products may be transferred.');
                }
            }
        }];
    }
}
