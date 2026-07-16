<?php

namespace App\Http\Requests;

use App\Enums\QuotationStatus;
use App\Models\Quotation;

class UpdateQuotationRequest extends StoreQuotationRequest
{
    public function authorize(): bool
    {
        $quotation = $this->route('quotation');

        return $quotation instanceof Quotation
            && $quotation->status === QuotationStatus::Draft
            && (bool) $this->user()?->can('update', $quotation);
    }
}
