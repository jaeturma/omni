<?php

namespace App\Http\Requests;

use App\Models\TaxObligation;
use Illuminate\Foundation\Http\FormRequest;

class AdjustTaxObligationDeadlineRequest extends FormRequest
{
    public function authorize(): bool
    {
        $obligation = $this->route('tax_obligation');

        return $obligation instanceof TaxObligation && (bool) $this->user()?->can('update', $obligation);
    }

    public function rules(): array
    {
        return [
            'adjusted_due_date' => ['required', 'date'],
            'reason' => ['required', 'string', 'max:5000'],
            'source_title' => ['required', 'string', 'max:255'],
            'source_url' => ['required', 'url:http,https', 'max:2048', function (string $attribute, mixed $value, \Closure $fail): void {
                $host = mb_strtolower((string) parse_url((string) $value, PHP_URL_HOST));
                if ($host !== 'bir.gov.ph' && ! str_ends_with($host, '.bir.gov.ph')) {
                    $fail('The deadline source URL must use an official bir.gov.ph domain.');
                }
            }],
        ];
    }
}
