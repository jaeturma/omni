<?php

namespace App\Http\Requests;

use App\Models\Bir2551qWorksheet;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveBir2551qWorksheetRequest extends FormRequest
{
    public function authorize(): bool
    {
        $worksheet = $this->route('bir_2551q_worksheet');
        if ($worksheet instanceof Bir2551qWorksheet) {
            return $this->routeIs('bir-2551q.revise')
                ? (bool) $this->user()?->can('revise', $worksheet)
                : (bool) $this->user()?->can('update', $worksheet);
        }

        return (bool) $this->user()?->can('bir-2551q.prepare');
    }

    public function rules(): array
    {
        $hasExclusions = count($this->input('excluded_source_keys', [])) > 0;
        $hasCredits = bccomp((string) ($this->input('allowable_credits') ?: '0'), '0', 4) > 0;
        $hasPriorPayment = bccomp((string) ($this->input('prior_payment') ?: '0'), '0', 4) > 0;
        $hasPenalties = collect(['surcharge', 'interest', 'compromise_penalty'])->contains(fn (string $field): bool => bccomp((string) ($this->input($field) ?: '0'), '0', 4) > 0);

        return [
            'basis_type' => ['required', Rule::in(['accrual', 'cash_receipt'])],
            'return_type' => ['required', Rule::in(['original', 'amended'])],
            'excluded_source_keys' => ['nullable', 'array'], 'excluded_source_keys.*' => ['required', 'string', 'max:100', 'distinct'],
            'exclusion_reason' => [Rule::requiredIf($hasExclusions), 'nullable', 'string', 'max:2000'],
            'exclusion_evidence' => [Rule::requiredIf($hasExclusions), 'nullable', 'string', 'max:255'],
            'allowable_credits' => ['nullable', 'decimal:0,4', 'gte:0'],
            'credits_authority' => [Rule::requiredIf($hasCredits), 'nullable', 'string', 'max:2000'],
            'credits_evidence' => [Rule::requiredIf($hasCredits), 'nullable', 'string', 'max:255'],
            'prior_payment' => ['nullable', 'decimal:0,4', 'gte:0'],
            'prior_payment_reference' => [Rule::requiredIf($hasPriorPayment), 'nullable', 'string', 'max:255'],
            'surcharge' => ['nullable', 'decimal:0,4', 'gte:0'], 'interest' => ['nullable', 'decimal:0,4', 'gte:0'],
            'compromise_penalty' => ['nullable', 'decimal:0,4', 'gte:0'],
            'penalty_authority' => [Rule::requiredIf($hasPenalties), 'nullable', 'string', 'max:2000'],
            'penalty_evidence' => [Rule::requiredIf($hasPenalties), 'nullable', 'string', 'max:255'],
            'preparation_notes' => ['nullable', 'string', 'max:5000'],
            'revision_reason' => [Rule::requiredIf($this->routeIs('bir-2551q.revise')), 'nullable', 'string', 'max:2000'],
        ];
    }
}
