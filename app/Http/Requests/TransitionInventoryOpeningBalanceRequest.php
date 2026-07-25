<?php

namespace App\Http\Requests;

use App\Enums\InventoryOpeningStatus;
use App\Models\InventoryOpeningBalance;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TransitionInventoryOpeningBalanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        $opening = $this->route('inventory_opening_balance');
        $target = InventoryOpeningStatus::tryFrom((string) $this->input('status'));

        return $opening instanceof InventoryOpeningBalance && $target
            ? (bool) $this->user()?->can($target === InventoryOpeningStatus::Posted ? 'post' : 'void', $opening)
            : false;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::enum(InventoryOpeningStatus::class), Rule::in(['posted', 'voided'])],
            'reason' => [Rule::requiredIf($this->input('status') === 'voided'), 'nullable', 'string', 'max:1000'],
        ];
    }
}
