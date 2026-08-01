<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class GenerateTaxCalendarRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('tax-calendar.generate');
    }

    public function rules(): array
    {
        return ['from_year' => ['required', 'integer', 'between:2026,2100'], 'through_year' => ['required', 'integer', 'gte:from_year', 'max:2100']];
    }
}
