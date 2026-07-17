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

        return $this->input('status') === GovernmentDeductionStatus::Voided->value
            ? (bool) $this->user()?->can('void', $deduction)
            : (bool) $this->user()?->can('verify', $deduction);
    }

    public function rules(): array
    {
        return ['status' => ['required', Rule::in([GovernmentDeductionStatus::Verified->value, GovernmentDeductionStatus::Voided->value])],
            'reason' => [Rule::requiredIf($this->input('status') === GovernmentDeductionStatus::Voided->value), 'nullable', 'string', 'max:2000']];
    }
}
