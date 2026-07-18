<?php

namespace App\Http\Requests;

use App\Enums\PurchaseOrderStatus;
use App\Models\PurchaseOrder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TransitionPurchaseOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        $order = $this->route('purchase_order');
        if (! $order instanceof PurchaseOrder) {
            return false;
        }

        return match ($this->input('status')) {
            'approved' => (bool) $this->user()?->can('approve', $order), 'issued' => (bool) $this->user()?->can('send', $order), 'cancelled' => (bool) $this->user()?->can('cancel', $order), default => false,
        };
    }

    public function rules(): array
    {
        return ['status' => ['required', Rule::in([PurchaseOrderStatus::Approved->value, PurchaseOrderStatus::Issued->value, PurchaseOrderStatus::Cancelled->value])], 'reason' => [Rule::requiredIf($this->input('status') === PurchaseOrderStatus::Cancelled->value), 'nullable', 'string', 'max:2000']];
    }
}
