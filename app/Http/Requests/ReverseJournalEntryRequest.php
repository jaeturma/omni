<?php

namespace App\Http\Requests;

use App\Models\JournalEntry;
use Illuminate\Foundation\Http\FormRequest;

class ReverseJournalEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        $entry = $this->route('journal_entry');

        return $entry instanceof JournalEntry
            && $this->user()->can('reverse', $entry)
            && (! $this->boolean('auto_reverse') || $this->user()->can('autoReverse', $entry));
    }

    public function rules(): array
    {
        return [
            'reversal_date' => ['required', 'date'],
            'fiscal_period_id' => ['required', 'integer', 'exists:fiscal_periods,id'],
            'reason' => ['required', 'string', 'max:2000'],
            'auto_reverse' => ['sometimes', 'boolean'],
        ];
    }
}
