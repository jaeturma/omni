<?php

namespace App\Http\Requests;

use App\Enums\PurchaseRequestStatus;
use App\Models\PurchaseRequest;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TransitionPurchaseRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        $request = $this->route('purchase_request');
        if (! $request instanceof PurchaseRequest) {
            return false;
        }

        return match ($this->input('status')) {
            'approved', 'rejected' => (bool) $this->user()?->can('approve', $request),
            'cancelled' => (bool) $this->user()?->can('cancel', $request),
            default => (bool) $this->user()?->can('update', $request),
        };
    }

    public function rules(): array
    {
        return ['status' => ['required', Rule::enum(PurchaseRequestStatus::class), Rule::notIn([PurchaseRequestStatus::Converted->value])], 'reason' => [Rule::requiredIf(in_array($this->input('status'), ['rejected', 'cancelled'], true)), 'nullable', 'string', 'max:2000']];
    }
}
