<?php

namespace App\Http\Requests;

use App\Models\TaxProfile;
use App\Models\TaxRateSetting;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreTaxRateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $profile = TaxProfile::query()->where('active', true)->first();

        return $profile && (bool) $this->user()?->can('tax-rates.manage');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'tax_type' => ['required', 'string', 'max:100'], 'rate' => ['required', 'decimal:0,6', 'between:0,100'],
            'effective_from' => ['required', 'date'], 'effective_to' => ['nullable', 'date', 'after_or_equal:effective_from'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            $profile = TaxProfile::query()->where('active', true)->first();
            if (! $profile || $validator->errors()->isNotEmpty()) {
                return;
            }
            $overlaps = TaxRateSetting::query()->whereBelongsTo($profile)->where('tax_type', $this->string('tax_type'))
                ->whereDate('effective_from', '<=', $this->input('effective_to') ?: '9999-12-31')
                ->where(fn ($query) => $query->whereNull('effective_to')->orWhereDate('effective_to', '>=', $this->input('effective_from')))->exists();
            if ($overlaps) {
                $validator->errors()->add('effective_from', 'The effective period overlaps an existing rate.');
            }
        }];
    }
}
