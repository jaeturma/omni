<?php

namespace App\Http\Requests;

use App\Reports\ReceivablesReport;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReceivableReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->routeIs('customer-statements.*')
            ? (bool) $this->user()?->can('customer-statements.view')
            : (bool) $this->user()?->can('receivables.view');
    }

    public function rules(): array
    {
        return ['as_of' => ['required', 'date'], 'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
            'customer_type' => ['nullable', Rule::in(['private', 'government'])],
            'state' => ['nullable', Rule::in(['open', 'partial', 'overdue'])],
            'bucket' => ['nullable', Rule::in(ReceivablesReport::BUCKETS)]];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['as_of' => $this->input('as_of', now()->toDateString())]);
    }
}
