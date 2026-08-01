<?php

namespace App\Http\Requests;

use App\Models\TaxComplianceRule;
use Illuminate\Foundation\Http\FormRequest;

class ReviewTaxComplianceRuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        $rule = $this->route('tax_compliance_rule');

        return $rule instanceof TaxComplianceRule && (bool) $this->user()?->can('review', $rule);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'official_reference_title' => ['required', 'string', 'max:255'],
            'official_reference_url' => ['required', 'url:http,https', 'max:2048', function (string $attribute, mixed $value, \Closure $fail): void {
                $host = mb_strtolower((string) parse_url((string) $value, PHP_URL_HOST));
                if ($host !== 'bir.gov.ph' && ! str_ends_with($host, '.bir.gov.ph')) {
                    $fail('The official reference URL must use an official bir.gov.ph domain.');
                }
            }],
            'last_reviewed_on' => ['required', 'date', 'before_or_equal:today'],
            'reviewer_notes' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
