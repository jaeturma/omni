<?php

namespace App\Http\Requests;

use App\Models\TaxReconciliationAdjustment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReviewTaxReconciliationAdjustmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $adjustment = $this->route('tax_reconciliation_adjustment');

        return $adjustment instanceof TaxReconciliationAdjustment && (bool) $this->user()?->can('review', $adjustment->reconciliation);
    }

    public function rules(): array
    {
        return ['status' => ['required', Rule::in(['approved', 'rejected'])], 'review_notes' => ['nullable', 'string', 'max:2000']];
    }
}
