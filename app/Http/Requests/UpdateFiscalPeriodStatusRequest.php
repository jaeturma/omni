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
        $ability = $this->input('status') === 'locked' ? 'lock' : 'close';

        return $period instanceof FiscalPeriod && (bool) $this->user()?->can($ability, $period);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in(['closed', 'locked'])],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            $period = $this->route('fiscalPeriod');
            if (! $period instanceof FiscalPeriod || $validator->errors()->isNotEmpty()) {
                return;
            }
            if ($period->status === 'locked') {
                $validator->errors()->add('status', 'A locked period cannot be changed.');
            }
            if ($this->input('status') === 'locked' && $period->status !== 'closed') {
                $validator->errors()->add('status', 'Close the period before locking it.');
            }
        }];
    }
}
