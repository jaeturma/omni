<?php

namespace App\Http\Requests;

use App\Models\BusinessProfile;
use Illuminate\Foundation\Http\FormRequest;

class UpsertBusinessProfileRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $profile = BusinessProfile::active()->first();

        return $profile
            ? (bool) $this->user()?->can('update', $profile)
            : (bool) $this->user()?->can('create', BusinessProfile::class);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'registered_business_name' => ['required', 'string', 'max:255'],
            'trade_name' => ['required', 'string', 'max:255'],
            'proprietor_name' => ['required', 'string', 'max:255'],
            'tin' => ['required', 'regex:/^\d{3}-?\d{3}-?\d{3}(?:-?\d{3,5})?$/'],
            'branch_code' => ['required', 'regex:/^\d{3,5}$/'],
            'rdo_code' => ['required', 'regex:/^\d{3,5}$/'],
            'registration_date' => ['required', 'date'],
            'business_start_date' => ['required', 'date'],
            'registered_address' => ['required', 'string', 'max:2000'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'website' => ['nullable', 'url:http,https', 'max:255'],
            'default_currency' => ['required', 'string', 'size:3'],
            'timezone' => ['required', 'timezone:all'],
            'fiscal_year_start_month' => ['required', 'integer', 'between:1,12'],
            'logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'active' => ['required', 'boolean'],
        ];
    }
}
