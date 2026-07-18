<?php

namespace App\Http\Requests;

use App\Enums\CashReceiptStatus;
use App\Models\CashReceipt;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TransitionCashReceiptRequest extends FormRequest
{
    public function authorize(): bool
    {
        $receipt = $this->route('cash_receipt');
        if (! $receipt instanceof CashReceipt) {
            return false;
        }

        return match ($this->input('status')) {
            'posted' => (bool) $this->user()?->can('post', $receipt), 'cleared' => (bool) $this->user()?->can('clear', $receipt),
            'bounced', 'voided' => (bool) $this->user()?->can('void', $receipt), default => false,
        };
    }

    public function rules(): array
    {
        return ['status' => ['required', Rule::enum(CashReceiptStatus::class)],
            'clearing_date' => [Rule::requiredIf($this->input('status') === 'cleared'), 'nullable', 'date'],
            'reason' => [Rule::requiredIf(in_array($this->input('status'), ['bounced', 'voided'], true)), 'nullable', 'string', 'max:2000']];
    }
}
