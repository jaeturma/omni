<?php

namespace App\Http\Requests;

use App\Models\BusinessProfile;
use App\Models\FiscalYear;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreFiscalYearRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('create', FiscalYear::class);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:50'],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['required', 'date', 'after_or_equal:starts_on'],
            'is_current' => ['required', 'boolean'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $business = BusinessProfile::active()->first();
            $overlap = FiscalYear::query()->when($business, fn ($query) => $query->whereBelongsTo($business))
                ->where('starts_on', '<=', $this->date('ends_on'))
                ->where('ends_on', '>=', $this->date('starts_on'))->exists();
            if ($overlap) {
                $validator->errors()->add('starts_on', 'The fiscal year overlaps an existing fiscal year.');
            }
        }];
    }
}
