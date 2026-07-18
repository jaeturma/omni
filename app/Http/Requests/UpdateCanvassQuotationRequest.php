<?php

namespace App\Http\Requests;

use App\Models\CanvassQuotation;

class UpdateCanvassQuotationRequest extends StoreCanvassQuotationRequest
{
    public function authorize(): bool
    {
        $quotation = $this->route('canvass_quotation');

        return $quotation instanceof CanvassQuotation && (bool) $this->user()?->can('update', $quotation);
    }
}
