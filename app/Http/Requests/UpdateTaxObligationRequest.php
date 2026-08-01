<?php

namespace App\Http\Requests;

use App\Models\TaxObligation;
use App\Services\TaxComplianceCalendar;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTaxObligationRequest extends FormRequest
{
    public function authorize(): bool
    {
        $obligation = $this->route('tax_obligation');
        if (! $obligation instanceof TaxObligation || ! $this->user()?->can('update', $obligation)) {
            return false;
        }

        return ! $this->filled('assigned_reviewer_id') || (bool) $this->user()->can('assignReviewer', $obligation);
    }

    public function rules(): array
    {
        return [
            'status' => ['sometimes', Rule::in(TaxComplianceCalendar::STATUSES)],
            'assigned_reviewer_id' => ['nullable', 'integer', Rule::exists('users', 'id')->where('active', true)],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
