<?php

namespace App\Http\Requests;

use App\Enums\ReceivingStatus;
use App\Models\ReceivingRecord;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TransitionReceivingRecordRequest extends FormRequest
{
    public function authorize(): bool
    {
        $record = $this->route('receiving_record');
        if (! $record instanceof ReceivingRecord) {
            return false;
        }

        return match ($this->input('status')) {
            'received' => (bool) $this->user()?->can('receive', $record), 'inspected' => (bool) $this->user()?->can('inspect', $record), 'accepted', 'partially_accepted', 'rejected' => (bool) $this->user()?->can('accept', $record), 'cancelled' => (bool) $this->user()?->can('cancel', $record), default => false
        };
    }

    public function rules(): array
    {
        return ['status' => ['required', Rule::enum(ReceivingStatus::class)], 'reason' => [Rule::requiredIf($this->input('status') === ReceivingStatus::Cancelled->value), 'nullable', 'string', 'max:2000']];
    }
}
