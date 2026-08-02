<?php

namespace App\Http\Requests;

use App\Enums\GovernmentDeductionStatus;
use App\Models\GovernmentDeduction;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TransitionGovernmentDeductionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $deduction = $this->route('government_deduction');
        if (! $deduction instanceof GovernmentDeduction) {
            return false;
        }

        return match ($this->input('status')) {
            GovernmentDeductionStatus::Voided->value => (bool) $this->user()?->can('void', $deduction),
            GovernmentDeductionStatus::Rejected->value => (bool) $this->user()?->can('reject', $deduction),
            default => (bool) $this->user()?->can('verify', $deduction),
        };
    }

    public function rules(): array
    {
        return ['status' => ['required', Rule::in([GovernmentDeductionStatus::Verified->value, GovernmentDeductionStatus::Rejected->value, GovernmentDeductionStatus::Voided->value])],
            'reason' => [Rule::requiredIf(in_array($this->input('status'), [GovernmentDeductionStatus::Rejected->value, GovernmentDeductionStatus::Voided->value], true)), 'nullable', 'string', 'max:2000']];
    }
}
