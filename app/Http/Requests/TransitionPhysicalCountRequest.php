<?php

namespace App\Http\Requests;

use App\Enums\PhysicalCountStatus;
use App\Models\PhysicalCount;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TransitionPhysicalCountRequest extends FormRequest
{
    public function authorize(): bool
    {
        $count = $this->route('physical_count');
        $target = PhysicalCountStatus::tryFrom((string) $this->input('status'));
        $ability = match ($target) {
            PhysicalCountStatus::Counting => $count?->status === PhysicalCountStatus::Draft ? 'count' : 'review',
            PhysicalCountStatus::Submitted => 'count',
            PhysicalCountStatus::Approved => 'approve',
            PhysicalCountStatus::Posted => 'post',
            PhysicalCountStatus::Voided => 'void',
            default => null,
        };

        return $count instanceof PhysicalCount && $ability
            ? (bool) $this->user()?->can($ability, $count)
            : false;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::enum(PhysicalCountStatus::class),
                Rule::in(['counting', 'submitted', 'approved', 'posted', 'voided'])],
            'reason' => [Rule::requiredIf($this->input('status') === 'voided'), 'nullable', 'string', 'max:1000'],
        ];
    }
}
