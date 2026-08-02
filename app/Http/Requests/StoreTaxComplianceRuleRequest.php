<?php

namespace App\Http\Requests;

use App\Models\TaxComplianceRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTaxComplianceRuleRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if ($this->has('attachment_requirements_text')) {
            $requirements = collect(preg_split('/\r\n|\r|\n/', (string) $this->input('attachment_requirements_text')))
                ->map(fn (string $requirement): string => trim($requirement))
                ->filter()
                ->values()
                ->all();
            $this->merge(['attachment_requirements' => $requirements]);
        }
        if ($this->filled('calculation_parameters_text')) {
            $decoded = json_decode($this->string('calculation_parameters_text')->toString(), true);
            $this->merge(['calculation_parameters' => is_array($decoded) ? $decoded : $this->input('calculation_parameters_text')]);
        } elseif ($this->has('calculation_parameters_text')) {
            $this->merge(['calculation_parameters' => null]);
        }
    }

    public function authorize(): bool
    {
        return (bool) $this->user()?->can('create', TaxComplianceRule::class);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'tax_type' => ['required', 'string', 'max:100'],
            'bir_form_number' => ['required', 'string', 'max:30'],
            'form_title' => ['required', 'string', 'max:255'],
            'taxpayer_applicability' => ['required', 'string', 'max:100'],
            'registration_applicability' => ['required', Rule::in(['any', 'registered', 'not_registered'])],
            'filing_frequency' => ['required', Rule::in(array_keys(config('tax_compliance.filing_frequencies')))],
            'applicable_quarters' => ['nullable', 'array'],
            'applicable_quarters.*' => ['integer', Rule::in([1, 2, 3, 4]), 'distinct'],
            'effective_from' => ['required', 'date'],
            'effective_to' => ['nullable', 'date', 'after_or_equal:effective_from'],
            'tax_rate' => ['nullable', 'decimal:0,6', 'between:0,100'],
            'tax_base_rule' => ['required', 'string', 'max:5000'],
            'credit_rule' => ['required', 'string', 'max:5000'],
            'calculation_parameters' => ['nullable', 'array'],
            'calculation_parameters_text' => ['nullable', 'json', 'max:20000'],
            'deadline_rule' => ['required', 'string', 'max:5000'],
            'deadline_months_after_period_end' => ['required', 'integer', 'between:0,24'],
            'deadline_day' => ['required', 'integer', 'between:1,31'],
            'amendment_supported' => ['required', 'boolean'],
            'attachment_requirements' => ['nullable', 'array'],
            'attachment_requirements.*' => ['required', 'string', 'max:255', 'distinct'],
            'attachment_requirements_text' => ['nullable', 'string', 'max:5000'],
            'official_reference_title' => ['required', 'string', 'max:255'],
            'official_reference_url' => ['required', 'url:http,https', 'max:2048', $this->officialBirUrl(...)],
            'last_reviewed_on' => ['required', 'date', 'before_or_equal:today'],
            'reviewer_notes' => ['nullable', 'string', 'max:5000'],
            'change_reason' => ['nullable', 'string', 'max:5000'],
            'active' => ['required', 'boolean'],
        ];
    }

    protected function officialBirUrl(string $attribute, mixed $value, \Closure $fail): void
    {
        $host = mb_strtolower((string) parse_url((string) $value, PHP_URL_HOST));
        if ($host !== 'bir.gov.ph' && ! str_ends_with($host, '.bir.gov.ph')) {
            $fail('The official reference URL must use an official bir.gov.ph domain.');
        }
    }
}
