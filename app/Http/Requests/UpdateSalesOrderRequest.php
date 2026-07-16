<?php

namespace App\Http\Requests;

use App\Enums\SalesOrderStatus;
use App\Models\SalesOrder;

class UpdateSalesOrderRequest extends StoreSalesOrderRequest
{
    public function authorize(): bool
    {
        $o = $this->route('sales_order');

        return $o instanceof SalesOrder && $o->status === SalesOrderStatus::Draft && (bool) $this->user()?->can('update', $o);
    }
}
