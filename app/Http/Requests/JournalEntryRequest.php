<?php

namespace App\Http\Requests;

use App\Enums\AccountingSourceType;
use App\Enums\JournalEntryType;
use App\Models\Account;
use App\Models\JournalEntry;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class JournalEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        $entry = $this->route('journal_entry');

        return $entry instanceof JournalEntry
            ? (bool) $this->user()?->can('update', $entry)
            : (bool) $this->user()?->can('create', JournalEntry::class);
    }

    public function rules(): array
    {
        $entry = $this->route('journal_entry');

        return [
            'journal_number' => ['required', 'string', 'max:40', Rule::unique(JournalEntry::class)->ignore($entry)],
            'journal_date' => ['required', 'date'],
            'document_date' => ['required', 'date'],
            'fiscal_period_id' => ['required', 'integer', 'exists:fiscal_periods,id'],
            'journal_type' => ['required', Rule::enum(JournalEntryType::class)],
            'source_type' => ['required', Rule::enum(AccountingSourceType::class)],
            'source_id' => ['nullable', 'required_unless:source_type,manual', 'integer', 'min:1'],
            'reference_number' => ['nullable', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:2000'],
            'lines' => ['required', 'array', 'min:2'],
            'lines.*.account_id' => ['required', 'integer', 'exists:accounts,id'],
            'lines.*.description' => ['nullable', 'string', 'max:2000'],
            'lines.*.debit' => ['required', 'decimal:0,4', 'min:0'],
            'lines.*.credit' => ['required', 'decimal:0,4', 'min:0'],
            'lines.*.customer_id' => ['nullable', 'integer', 'exists:customers,id'],
            'lines.*.supplier_id' => ['nullable', 'integer', 'exists:suppliers,id'],
            'lines.*.financial_account_id' => ['nullable', 'integer', 'exists:financial_accounts,id'],
            'lines.*.warehouse_id' => ['nullable', 'integer', 'exists:warehouses,id'],
            'lines.*.product_id' => ['nullable', 'integer', 'exists:product_services,id'],
            'lines.*.source_line_type' => ['nullable', 'string', 'max:40'],
            'lines.*.source_line_id' => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            foreach ($this->input('lines', []) as $index => $line) {
                $debit = (string) ($line['debit'] ?? '0');
                $credit = (string) ($line['credit'] ?? '0');
                if ((bccomp($debit, '0', 4) > 0) === (bccomp($credit, '0', 4) > 0)) {
                    $validator->errors()->add("lines.$index.debit", 'Each line must contain either a debit or a credit, but not both.');
                }
                $account = Account::query()->find($line['account_id'] ?? null);
                if ($account !== null && ($account->is_header || ! $account->is_postable || ! $account->is_active)) {
                    $validator->errors()->add("lines.$index.account_id", 'The account must be active and postable.');
                }
            }

            $sourceType = AccountingSourceType::tryFrom($this->string('source_type')->toString());
            if ($sourceType !== null && $sourceType !== AccountingSourceType::Manual && filled($this->input('source_id'))) {
                $entry = $this->route('journal_entry');
                $duplicate = JournalEntry::query()->where('source_type', $sourceType)->where('source_id', $this->integer('source_id'))
                    ->when($entry instanceof JournalEntry, fn ($query) => $query->whereKeyNot($entry->id))->exists();
                if ($duplicate) {
                    $validator->errors()->add('source_id', 'This source transaction has already been linked to a journal entry.');
                }
            }
        }];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'journal_number' => str($this->input('journal_number'))->trim()->upper()->toString(),
            'source_id' => $this->input('source_type') === AccountingSourceType::Manual->value ? null : $this->input('source_id'),
        ]);
    }
}
