<?php

namespace App\Http\Requests;

use App\Models\TaxReconciliation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreTaxReconciliationAdjustmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $reconciliation = $this->route('tax_reconciliation');

        return $reconciliation instanceof TaxReconciliation && (bool) $this->user()?->can('adjust', $reconciliation);
    }

    public function rules(): array
    {
        return ['amount' => ['required', 'decimal:0,4', 'not_in:0,0.0,0.00,0.000,0.0000'], 'reason' => ['required', 'string', 'max:2000'], 'evidence_reference' => ['required', 'string', 'max:255'], 'reviewer_id' => ['required', 'integer', Rule::exists('users', 'id')->where('active', true)]];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($this->integer('reviewer_id') === $this->user()?->id) {
                $validator->errors()->add('reviewer_id', 'The reviewer must be different from the adjustment creator.');
            }
        }];
    }
}
