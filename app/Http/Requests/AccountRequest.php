<?php

namespace App\Http\Requests;

use App\Enums\AccountClass;
use App\Enums\AccountType;
use App\Enums\NormalBalance;
use App\Models\Account;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

abstract class AccountRequest extends FormRequest
{
    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        $account = $this->route('account');

        return [
            'code' => ['required', 'string', 'max:30', 'regex:/^[A-Z0-9.-]+$/', Rule::unique(Account::class)->ignore($account)],
            'name' => ['required', 'string', 'max:255'],
            'account_class' => ['required', Rule::enum(AccountClass::class)],
            'account_type' => ['required', Rule::enum(AccountType::class)],
            'normal_balance' => ['required', Rule::enum(NormalBalance::class)],
            'parent_id' => ['nullable', 'integer', Rule::exists(Account::class, 'id')],
            'is_header' => ['required', 'boolean'],
            'is_postable' => ['required', 'boolean'],
            'is_control_account' => ['required', 'boolean'],
            'control_account_type' => ['nullable', 'required_if:is_control_account,1', 'string', 'max:30', Rule::unique(Account::class)->ignore($account)],
            'description' => ['nullable', 'string', 'max:2000'],
            'display_order' => ['required', 'integer', 'min:0', 'max:4294967295'],
        ];
    }

    /** @return array<callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $accountType = AccountType::tryFrom($this->string('account_type')->toString());
            $accountClass = AccountClass::tryFrom($this->string('account_class')->toString());
            $parent = Account::query()->find($this->integer('parent_id'));
            $account = $this->route('account');

            if ($accountType !== null && $accountClass !== $accountType->accountClass()) {
                $validator->errors()->add('account_type', 'The account type does not belong to the selected account class.');
            }
            if ($parent !== null && $accountClass?->value !== $parent->getRawOriginal('account_class')) {
                $validator->errors()->add('parent_id', 'The parent must belong to the same account class.');
            }
            if ($this->boolean('is_header') && $this->boolean('is_postable')) {
                $validator->errors()->add('is_postable', 'A header account cannot be postable.');
            }
            if (! $this->boolean('is_control_account') && filled($this->input('control_account_type'))) {
                $validator->errors()->add('control_account_type', 'Only control accounts may have a control account type.');
            }
            if ($account instanceof Account && $account->wouldCreateCycle($parent?->id)) {
                $validator->errors()->add('parent_id', 'The selected parent would create an account cycle.');
            }
            if ($account instanceof Account && ($account->is_system || $account->is_control_account)) {
                $protected = ['code', 'account_class', 'account_type', 'is_header', 'is_postable', 'is_control_account', 'control_account_type'];
                foreach ($protected as $attribute) {
                    if ((string) $account->getRawOriginal($attribute) !== (string) $this->input($attribute)) {
                        $validator->errors()->add($attribute, 'This classification is protected.');
                    }
                }
            }
        }];
    }

    protected function prepareForValidation(): void
    {
        $accountType = AccountType::tryFrom($this->string('account_type')->toString());
        $this->merge([
            'code' => str($this->input('code'))->trim()->upper()->toString(),
            'name' => str($this->input('name'))->squish()->toString(),
            'parent_id' => filled($this->input('parent_id')) ? $this->input('parent_id') : null,
            'normal_balance' => $accountType?->normalBalance()->value,
            'control_account_type' => filled($this->input('control_account_type'))
                ? str($this->input('control_account_type'))->trim()->lower()->toString()
                : null,
        ]);
    }
}
