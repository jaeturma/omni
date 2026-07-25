<?php

namespace App\Http\Requests;

use App\Enums\InventoryTransferStatus;
use App\Models\InventoryTransfer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TransitionInventoryTransferRequest extends FormRequest
{
    public function authorize(): bool
    {
        $transfer = $this->route('inventory_transfer');
        $target = InventoryTransferStatus::tryFrom((string) $this->input('status'));
        $ability = match ($target) {
            InventoryTransferStatus::Approved => 'approve',
            InventoryTransferStatus::Released, InventoryTransferStatus::InTransit => 'release',
            InventoryTransferStatus::Received, InventoryTransferStatus::Completed => 'receive',
            InventoryTransferStatus::Voided => 'void',
            default => null,
        };

        return $transfer instanceof InventoryTransfer && $ability
            ? (bool) $this->user()?->can($ability, $transfer)
            : false;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::enum(InventoryTransferStatus::class),
                Rule::in(['approved', 'released', 'in_transit', 'received', 'completed', 'voided'])],
            'reason' => [Rule::requiredIf($this->input('status') === 'voided'), 'nullable', 'string', 'max:1000'],
        ];
    }
}
