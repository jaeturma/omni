<?php

namespace App\Http\Requests;

use App\Enums\SalesOrderStatus;
use App\Models\SalesOrder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateSalesOrderFulfillmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $o = $this->route('sales_order');

        return $o instanceof SalesOrder && in_array($o->status, [SalesOrderStatus::Confirmed, SalesOrderStatus::PartiallyFulfilled], true) && (bool) $this->user()?->can('confirm', $o);
    }

    public function rules(): array
    {
        return ['quantities' => ['required', 'array'], 'quantities.*.delivered_quantity' => ['required', 'decimal:0,4', 'min:0'], 'quantities.*.invoiced_quantity' => ['required', 'decimal:0,4', 'min:0'], 'quantities.*.cancelled_quantity' => ['required', 'decimal:0,4', 'min:0']];
    }

    public function after(): array
    {
        return [function (Validator $v) {
            $o = $this->route('sales_order');
            if (! $o instanceof SalesOrder) {
                return;
            }foreach ($o->lines as $line) {
                $q = $this->input('quantities.'.$line->id);
                if (! $q || bccomp(bcadd((string) $q['delivered_quantity'], (string) $q['cancelled_quantity'], 4), $line->ordered_quantity, 4) === 1 || bccomp((string) $q['invoiced_quantity'], $line->ordered_quantity, 4) === 1) {
                    $v->errors()->add('quantities.'.$line->id, 'Fulfilled quantities cannot exceed ordered quantity.');
                }
            }
        }];
    }
}
