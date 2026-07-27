<?php

namespace App\Http\Requests;

use App\Enums\AccountingSourceType;
use App\Models\Account;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LedgerReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        $permission = match (true) {
            $this->routeIs('general-journal.*') => 'general-journal.view',
            $this->routeIs('account-activity.*') => 'account-activity.view',
            default => 'general-ledger.view',
        };

        if ($this->routeIs('*.export')) {
            return (bool) $this->user()->can($permission)
                && (bool) $this->user()->can('general-ledger.export');
        }

        $mayView = (bool) $this->user()->can($permission);

        return $this->routeIs('general-journal.*')
            ? $mayView
            : $mayView && (bool) $this->user()->can('account-balances.view');
    }

    public function rules(): array
    {
        return [
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'account_id' => [
                Rule::requiredIf($this->routeIs('account-activity.*')),
                'nullable', 'integer', 'exists:accounts,id',
            ],
            'include_descendants' => ['nullable', 'boolean'],
            'source_type' => ['nullable', Rule::enum(AccountingSourceType::class)],
            'reference' => ['nullable', 'string', 'max:255'],
            'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
            'supplier_id' => ['nullable', 'integer', 'exists:suppliers,id'],
            'financial_account_id' => ['nullable', 'integer', 'exists:financial_accounts,id'],
            'product_id' => ['nullable', 'integer', 'exists:product_services,id'],
            'warehouse_id' => ['nullable', 'integer', 'exists:warehouses,id'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'start_date' => $this->input('start_date', now()->startOfMonth()->toDateString()),
            'end_date' => $this->input('end_date', now()->toDateString()),
            'account_id' => $this->input('account_id', $this->routeIs('account-activity.*')
                ? Account::query()->where('is_postable', true)->ordered()->value('id')
                : null),
            'include_descendants' => $this->boolean('include_descendants'),
        ]);
    }
}
