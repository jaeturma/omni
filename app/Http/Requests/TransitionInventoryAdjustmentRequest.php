<?php

namespace App\Http\Requests;

use App\Enums\InventoryAdjustmentStatus;
use App\Models\InventoryAdjustment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TransitionInventoryAdjustmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $adjustment = $this->route('inventory_adjustment');
        $target = InventoryAdjustmentStatus::tryFrom((string) $this->input('status'));

        $ability = match ($target) {
            InventoryAdjustmentStatus::Approved => 'approve',
            InventoryAdjustmentStatus::Posted => 'post',
            InventoryAdjustmentStatus::Voided => 'void',
            default => null,
        };

        return $adjustment instanceof InventoryAdjustment && $ability
            ? (bool) $this->user()?->can($ability, $adjustment)
            : false;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::enum(InventoryAdjustmentStatus::class), Rule::in(['approved', 'posted', 'voided'])],
            'reason' => [Rule::requiredIf($this->input('status') === 'voided'), 'nullable', 'string', 'max:1000'],
        ];
    }
}
