<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GovernmentDeductionReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('government-deductions.view');
    }

    public function rules(): array
    {
        return ['year' => ['required', 'integer', 'between:2000,2100'], 'quarter' => ['nullable', 'integer', 'between:1,4'],
            'customer_id' => ['nullable', 'integer', Rule::exists('customers', 'id')->where('type', 'government')],
            'status' => ['nullable', Rule::in(['pending', 'received', 'verified', 'voided'])], 'missing_certificate' => ['nullable', 'boolean']];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['year' => $this->input('year', now()->year)]);
    }
}
