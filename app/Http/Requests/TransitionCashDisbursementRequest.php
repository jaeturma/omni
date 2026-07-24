<?php

namespace App\Http\Requests;

use App\Enums\CashDisbursementStatus;
use App\Models\CashDisbursement;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TransitionCashDisbursementRequest extends FormRequest
{
    public function authorize(): bool
    {
        $disbursement = $this->route('cash_disbursement');
        if (! $disbursement instanceof CashDisbursement) {
            return false;
        }

        return match ($this->input('status')) {
            'posted' => (bool) $this->user()?->can('post', $disbursement),
            'released' => (bool) $this->user()?->can('release', $disbursement),
            'cleared' => (bool) $this->user()?->can('clear', $disbursement),
            'stopped' => (bool) $this->user()?->can('stop', $disbursement),
            'voided' => (bool) $this->user()?->can('void', $disbursement),
            default => false,
        };
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::enum(CashDisbursementStatus::class)],
            'release_date' => [Rule::requiredIf($this->input('status') === 'released'), 'nullable', 'date'],
            'clearing_date' => [Rule::requiredIf($this->input('status') === 'cleared'), 'nullable', 'date'],
            'reason' => [Rule::requiredIf(in_array($this->input('status'), ['stopped', 'voided'], true)), 'nullable', 'string', 'max:2000'],
        ];
    }
}
