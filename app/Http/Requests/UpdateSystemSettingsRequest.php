<?php

namespace App\Http\Requests;

use App\Services\SystemSettings;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateSystemSettingsRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('system-settings.update');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = ['settings' => ['required', 'array:'.implode(',', array_keys(SystemSettings::DEFAULTS))]];
        foreach (SystemSettings::rules() as $key => $keyRules) {
            $rules["settings.{$key}"] = $keyRules;
        }

        return $rules;
    }
}
