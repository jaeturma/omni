<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class DataSubjectLookupRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $permission = $this->routeIs('privacy.data-subjects.export') ? 'sensitive-data.export' : 'privacy-settings.view';

        return $this->user()?->can($permission) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'q' => [$this->routeIs('privacy.data-subjects.export') ? 'required' : 'nullable', 'string', 'min:3', 'max:120'],
        ];
    }
}
