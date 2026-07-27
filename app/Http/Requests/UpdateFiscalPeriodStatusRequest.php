<?php

namespace App\Http\Requests;

use App\Models\FiscalPeriod;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateFiscalPeriodStatusRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $period = $this->route('fiscalPeriod');
        $ability = match ($this->input('status')) {
            'locked' => 'lock',
            'open' => 'reopen',
            default => 'close',
        };

        return $period instanceof FiscalPeriod && $this->user()->can($ability, $period);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in(['open', 'closed', 'locked'])],
            'notes' => [
                Rule::requiredIf($this->input('status') === 'open' || $this->boolean('override_open_adjustments')),
                'nullable', 'string', 'max:4000',
            ],
            'override_open_adjustments' => ['nullable', 'boolean'],
            'lock_version' => ['required', 'integer', 'min:0'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            $period = $this->route('fiscalPeriod');
            if (! $period instanceof FiscalPeriod || $validator->errors()->isNotEmpty()) {
                return;
            }
            if ($this->input('status') === 'locked' && $period->status !== 'closed') {
                $validator->errors()->add('status', 'Close the period before locking it.');
            }
            if ($this->input('status') === 'closed' && $period->status !== 'open') {
                $validator->errors()->add('status', 'Only an open period may be closed.');
            }
            if ($this->input('status') === 'open' && ! in_array($period->status, ['closed', 'locked'], true)) {
                $validator->errors()->add('status', 'Only a closed or locked period may be reopened.');
            }
        }];
    }
}
