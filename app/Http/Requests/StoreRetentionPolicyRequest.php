<?php

namespace App\Http\Requests;

use App\Support\DataClassificationRegistry;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRetentionPolicyRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('privacy-settings.manage') ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'record_type' => ['required', 'string', 'max:120'],
            'classification' => ['required', Rule::in(DataClassificationRegistry::LEVELS)],
            'retention_months' => ['nullable', 'integer', 'min:1', 'max:1200', 'required_unless:disposition,retain_permanently'],
            'retention_trigger' => ['required', 'string', 'max:80'],
            'disposition' => ['required', Rule::in(['archive', 'anonymize', 'review_for_disposal', 'retain_permanently'])],
            'legal_basis' => ['required', 'string', 'min:10', 'max:2000'],
            'active' => ['required', 'boolean'],
            'reviewed_at' => ['required', 'date', 'before_or_equal:today'],
        ];
    }
}
