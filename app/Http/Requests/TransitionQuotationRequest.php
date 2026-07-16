<?php

namespace App\Http\Requests;

use App\Enums\QuotationStatus;
use App\Models\Quotation;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TransitionQuotationRequest extends FormRequest
{
    public function authorize(): bool
    {
        $quotation = $this->route('quotation');
        if (! $quotation instanceof Quotation) {
            return false;
        }

        return match ($this->input('status')) {
            QuotationStatus::Approved->value, QuotationStatus::Rejected->value, QuotationStatus::Expired->value => (bool) $this->user()?->can('approve', $quotation),
            QuotationStatus::Cancelled->value => (bool) $this->user()?->can('cancel', $quotation),
            default => (bool) $this->user()?->can('update', $quotation),
        };
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::enum(QuotationStatus::class)],
            'reason' => [Rule::requiredIf(in_array($this->input('status'), [QuotationStatus::Cancelled->value, QuotationStatus::Rejected->value], true)), 'nullable', 'string', 'max:2000'],
        ];
    }
}
