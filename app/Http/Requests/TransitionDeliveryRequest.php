<?php

namespace App\Http\Requests;

use App\Enums\DeliveryStatus;
use App\Models\Delivery;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TransitionDeliveryRequest extends FormRequest
{
    public function authorize(): bool
    {
        $d = $this->route('delivery');
        if (! $d instanceof Delivery) {
            return false;
        }

        return match ($this->input('status')) {
            DeliveryStatus::Released->value => (bool) $this->user()?->can('release', $d),DeliveryStatus::Accepted->value => (bool) $this->user()?->can('accept', $d),DeliveryStatus::Cancelled->value => (bool) $this->user()?->can('cancel', $d),default => (bool) $this->user()?->can('release', $d)
        };
    }

    public function rules(): array
    {
        return ['status' => ['required', Rule::enum(DeliveryStatus::class)], 'reason' => [Rule::requiredIf($this->input('status') === DeliveryStatus::Cancelled->value), 'nullable', 'string', 'max:2000'], 'received_by_name' => [Rule::requiredIf($this->input('status') === DeliveryStatus::Delivered->value), 'nullable', 'string', 'max:255'], 'received_at' => [Rule::requiredIf($this->input('status') === DeliveryStatus::Delivered->value), 'nullable', 'date'], 'acceptance_notes' => ['nullable', 'string', 'max:2000']];
    }
}
