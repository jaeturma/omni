<?php

namespace App\Http\Requests;

use App\Enums\PurchaseOrderStatus;
use App\Models\PurchaseOrder;

class UpdatePurchaseOrderRequest extends StorePurchaseOrderRequest
{
    public function authorize(): bool
    {
        $order = $this->route('purchase_order');

        return $order instanceof PurchaseOrder && $order->status === PurchaseOrderStatus::Draft && (bool) $this->user()?->can('update', $order);
    }
}
