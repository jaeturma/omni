<?php

namespace App\Http\Requests;

use App\Models\TaxComplianceRule;
use Illuminate\Validation\Rule;

class UpdateTaxComplianceRuleRequest extends StoreTaxComplianceRuleRequest
{
    public function authorize(): bool
    {
        $rule = $this->route('tax_compliance_rule');

        return $rule instanceof TaxComplianceRule && (bool) $this->user()?->can('update', $rule);
    }

    public function rules(): array
    {
        $rules = parent::rules();
        $rule = $this->route('tax_compliance_rule');
        $rules['change_reason'] = [
            Rule::requiredIf($rule instanceof TaxComplianceRule && $rule->used_at !== null),
            'nullable', 'string', 'max:5000',
        ];

        return $rules;
    }
}
