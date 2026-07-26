<?php

namespace App\Http\Requests;

use App\Enums\PostingSourceType;
use App\Models\Account;
use App\Models\Category;
use App\Models\PostingRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class PostingRuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        $postingRule = $this->route('posting_rule');

        return $postingRule instanceof PostingRule
            ? (bool) $this->user()?->can('update', $postingRule)
            : (bool) $this->user()?->can('create', PostingRule::class);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'source_type' => ['required', Rule::enum(PostingSourceType::class)],
            'debit_account_id' => ['required', 'integer', Rule::exists(Account::class, 'id')],
            'credit_account_id' => ['required', 'integer', Rule::exists(Account::class, 'id'), 'different:debit_account_id'],
            'product_category_id' => ['nullable', 'integer', Rule::exists(Category::class, 'id')->where('type', 'product')],
            'service_category_id' => ['nullable', 'integer', Rule::exists(Category::class, 'id')->where('type', 'service')],
            'expense_category' => ['nullable', 'string', 'max:50'],
            'customer_type' => ['nullable', 'string', 'max:50'],
            'supplier_type' => ['nullable', 'string', 'max:50'],
            'financial_account_id' => ['nullable', 'integer', 'exists:financial_accounts,id'],
            'tax_code' => ['nullable', 'string', 'max:40'],
            'warehouse_id' => ['nullable', 'integer', 'exists:warehouses,id'],
            'effective_from' => ['required', 'date'],
            'effective_to' => ['nullable', 'date', 'after_or_equal:effective_from'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            foreach (['debit_account_id', 'credit_account_id'] as $field) {
                $account = Account::query()->find($this->integer($field));
                if ($account !== null && ($account->is_header || ! $account->is_postable || ! $account->is_active)) {
                    $validator->errors()->add($field, 'The mapped account must be active and postable.');
                }
            }
        }];
    }

    protected function prepareForValidation(): void
    {
        $normalized = ['name' => str($this->input('name'))->squish()->toString()];
        foreach (PostingRule::DIMENSIONS as $dimension) {
            $value = $this->input($dimension);
            $normalized[$dimension] = filled($value)
                ? (str_ends_with($dimension, '_id') ? $value : str($value)->trim()->lower()->toString())
                : null;
        }
        $normalized['effective_to'] = filled($this->input('effective_to')) ? $this->input('effective_to') : null;
        $this->merge($normalized);
    }
}
