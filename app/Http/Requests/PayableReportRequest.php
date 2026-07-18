<?php

namespace App\Http\Requests;

use App\Reports\AccountsPayableReport;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PayableReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->routeIs('supplier-statements.*')
            ? (bool) $this->user()?->can('supplier-statements.view')
            : (bool) $this->user()?->can('payables.view');
    }

    public function rules(): array
    {
        return ['as_of' => ['required', 'date'], 'supplier_id' => ['nullable', 'integer', 'exists:suppliers,id'],
            'state' => ['nullable', Rule::in(['open', 'partial', 'overdue'])],
            'bucket' => ['nullable', Rule::in(AccountsPayableReport::BUCKETS)]];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['as_of' => $this->input('as_of', now()->toDateString())]);
    }
}
