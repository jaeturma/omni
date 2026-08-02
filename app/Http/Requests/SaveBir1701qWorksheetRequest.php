<?php

namespace App\Http\Requests;

use App\Models\Bir1701qWorksheet;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveBir1701qWorksheetRequest extends FormRequest
{
    public function authorize(): bool
    {
        $worksheet = $this->route('bir_1701q_worksheet');
        if ($worksheet instanceof Bir1701qWorksheet) {
            return $this->routeIs('bir-1701q.revise')
                ? (bool) $this->user()?->can('revise', $worksheet)
                : (bool) $this->user()?->can('update', $worksheet);
        }

        return (bool) $this->user()?->can('bir-1701q.prepare');
    }

    public function rules(): array
    {
        $positive = fn (string $field): bool => bccomp((string) ($this->input($field) ?: '0'), '0', 4) > 0;
        $adjusted = collect(['manual_deduction_adjustment', 'taxable_income_adjustment'])->contains($positive);
        $penalized = collect(['surcharge', 'interest', 'compromise_penalty'])->contains($positive);

        return [
            'return_type' => ['required', Rule::in(['original', 'amended'])],
            'manual_deduction_adjustment' => ['nullable', 'decimal:0,4'],
            'taxable_income_adjustment' => ['nullable', 'decimal:0,4'],
            'manual_adjustment_reason' => [Rule::requiredIf($adjusted), 'nullable', 'string', 'max:2000'],
            'manual_adjustment_evidence' => [Rule::requiredIf($adjusted), 'nullable', 'string', 'max:255'],
            'prior_quarter_payments' => ['nullable', 'decimal:0,4', 'gte:0'],
            'prior_payment_evidence' => [Rule::requiredIf($positive('prior_quarter_payments')), 'nullable', 'string', 'max:255'],
            'manual_creditable_withholding' => ['nullable', 'decimal:0,4', 'gte:0'],
            'withholding_evidence' => [Rule::requiredIf($positive('manual_creditable_withholding')), 'nullable', 'string', 'max:255'],
            'other_allowable_credits' => ['nullable', 'decimal:0,4', 'gte:0'],
            'other_credits_authority' => [Rule::requiredIf($positive('other_allowable_credits')), 'nullable', 'string', 'max:2000'],
            'other_credits_evidence' => [Rule::requiredIf($positive('other_allowable_credits')), 'nullable', 'string', 'max:255'],
            'surcharge' => ['nullable', 'decimal:0,4', 'gte:0'],
            'interest' => ['nullable', 'decimal:0,4', 'gte:0'],
            'compromise_penalty' => ['nullable', 'decimal:0,4', 'gte:0'],
            'penalty_authority' => [Rule::requiredIf($penalized), 'nullable', 'string', 'max:2000'],
            'penalty_evidence' => [Rule::requiredIf($penalized), 'nullable', 'string', 'max:255'],
            'preparation_notes' => ['nullable', 'string', 'max:5000'],
            'revision_reason' => [Rule::requiredIf($this->routeIs('bir-1701q.revise')), 'nullable', 'string', 'max:2000'],
        ];
    }
}
