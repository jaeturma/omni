<?php

namespace App\Http\Requests;

use App\Enums\PurchaseRequestStatus;
use App\Models\PurchaseRequest;

class UpdatePurchaseRequestRequest extends StorePurchaseRequestRequest
{
    public function authorize(): bool
    {
        $request = $this->route('purchase_request');

        return $request instanceof PurchaseRequest && $request->status === PurchaseRequestStatus::Draft && (bool) $this->user()?->can('update', $request);
    }
}
