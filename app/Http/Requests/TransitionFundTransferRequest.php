<?php

namespace App\Http\Requests;

use App\Enums\FundTransferStatus;
use App\Models\FundTransfer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TransitionFundTransferRequest extends FormRequest
{
    public function authorize(): bool
    {
        $transfer = $this->route('fund_transfer');
        if (! $transfer instanceof FundTransfer) {
            return false;
        }

        return match ($this->input('status')) {
            'posted' => (bool) $this->user()?->can('post', $transfer),
            'completed' => (bool) $this->user()?->can('complete', $transfer),
            'failed' => (bool) $this->user()?->can('fail', $transfer),
            'voided' => (bool) $this->user()?->can('void', $transfer),
            default => false,
        };
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::enum(FundTransferStatus::class)],
            'reason' => [Rule::requiredIf(in_array($this->input('status'), ['failed', 'voided'], true)), 'nullable', 'string', 'max:2000'],
        ];
    }
}
