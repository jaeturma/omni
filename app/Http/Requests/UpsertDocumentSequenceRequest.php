<?php

namespace App\Http\Requests;

use App\Models\BusinessProfile;
use App\Models\DocumentSequence;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpsertDocumentSequenceRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge(['prefix' => $this->input('prefix') ?? '', 'suffix' => $this->input('suffix') ?? '']);
    }

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $sequence = $this->route('documentSequence');

        return $sequence instanceof DocumentSequence
            ? (bool) $this->user()?->can('update', $sequence)
            : (bool) $this->user()?->can('create', DocumentSequence::class);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'document_type' => ['required', Rule::in(DocumentSequence::TYPES)],
            'prefix' => ['nullable', 'string', 'max:50'],
            'suffix' => ['nullable', 'string', 'max:50'],
            'current_number' => ['required', 'integer', 'min:0'],
            'padding' => ['required', 'integer', 'between:1,12'],
            'reset_rule' => ['required', Rule::in(['never', 'fiscal_year'])],
            'fiscal_year_id' => ['nullable', 'integer', 'exists:fiscal_years,id'],
            'active' => ['required', 'boolean'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($this->input('reset_rule') === 'fiscal_year' && ! $this->filled('fiscal_year_id')) {
                $validator->errors()->add('fiscal_year_id', 'A fiscal year is required for fiscal-year resets.');
            }
            if ((str_contains((string) $this->input('prefix'), '{YYYY}') || str_contains((string) $this->input('suffix'), '{YYYY}')) && ! $this->filled('fiscal_year_id')) {
                $validator->errors()->add('fiscal_year_id', 'A fiscal year is required when the format uses {YYYY}.');
            }
            $sequence = $this->route('documentSequence');
            $duplicate = DocumentSequence::query()
                ->when($sequence instanceof DocumentSequence, fn ($query) => $query->whereKeyNot($sequence->id))
                ->where('business_profile_id', BusinessProfile::active()->value('id'))
                ->where('document_type', $this->input('document_type'))
                ->where('fiscal_year_scope', $this->input('fiscal_year_id') ?: 0)->exists();
            if ($duplicate) {
                $validator->errors()->add('document_type', 'A sequence already exists for this document type and fiscal-year scope.');
            }
            if ($sequence instanceof DocumentSequence && (int) $this->input('current_number') < (int) $sequence->reservations()->max('number')) {
                $validator->errors()->add('current_number', 'The current number cannot precede issued history.');
            }
        }];
    }
}
