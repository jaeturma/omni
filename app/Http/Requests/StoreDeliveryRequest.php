<?php

namespace App\Http\Requests;

use App\Enums\SalesOrderStatus;
use App\Models\Delivery;
use App\Models\SalesOrder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreDeliveryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('create', Delivery::class);
    }

    public function rules(): array
    {
        return ['sales_order_id' => ['required', Rule::exists('sales_orders', 'id')], 'warehouse_id' => ['nullable', Rule::exists('warehouses', 'id')->where('status', 'active')], 'delivery_date' => ['required', 'date'], 'delivery_address' => ['required', 'string', 'max:5000'], 'recipient_name' => ['nullable', 'string', 'max:255'], 'recipient_contact' => ['nullable', 'string', 'max:100'], 'inspection_reference' => ['nullable', 'string', 'max:255'], 'notes' => ['nullable', 'string', 'max:5000'], 'lines' => ['required', 'array', 'min:1'], 'lines.*.sales_order_line_id' => ['required', 'distinct', 'exists:sales_order_lines,id'], 'lines.*.delivered_quantity' => ['required', 'decimal:0,4', 'gt:0']];
    }

    public function after(): array
    {
        return [function (Validator $v) {
            $o = SalesOrder::find($this->input('sales_order_id'));
            if (! $o || ! in_array($o->status, [SalesOrderStatus::Confirmed, SalesOrderStatus::PartiallyFulfilled], true)) {
                $v->errors()->add('sales_order_id', 'Select a confirmed order with quantities remaining.');
            }foreach ($this->input('lines', []) as $i => $line) {
                if ($o && ! $o->lines()->whereKey($line['sales_order_line_id'] ?? 0)->exists()) {
                    $v->errors()->add('lines.'.$i.'.sales_order_line_id', 'The line must belong to the selected order.');
                }
            }
        }];
    }
}
