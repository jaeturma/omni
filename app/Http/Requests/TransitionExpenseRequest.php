<?php

namespace App\Http\Requests;

use App\Enums\ExpenseStatus;
use App\Models\Expense;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TransitionExpenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        $expense = $this->route('expense');
        if (! $expense instanceof Expense) {
            return false;
        }

        return match ($this->input('status')) {
            ExpenseStatus::Approved->value, ExpenseStatus::Reimbursable->value => (bool) $this->user()?->can('approve', $expense), ExpenseStatus::Paid->value => (bool) $this->user()?->can('pay', $expense), ExpenseStatus::Voided->value => (bool) $this->user()?->can('void', $expense), default => false
        };
    }

    public function rules(): array
    {
        $paying = $this->input('status') === ExpenseStatus::Paid->value;

        return ['status' => ['required', Rule::enum(ExpenseStatus::class)->only([ExpenseStatus::Approved, ExpenseStatus::Paid, ExpenseStatus::Reimbursable, ExpenseStatus::Voided])], 'reason' => [Rule::requiredIf($this->input('status') === ExpenseStatus::Voided->value), 'nullable', 'string', 'max:2000'], 'payment_method_id' => [Rule::requiredIf($paying), 'nullable', Rule::exists('payment_methods', 'id')->where('status', 'active')], 'bank_id' => ['nullable', Rule::exists('banks', 'id')->where('status', 'active')], 'withholding_amount' => [Rule::requiredIf($paying), 'nullable', 'decimal:0,4', 'gte:0'], 'other_deductions' => [Rule::requiredIf($paying), 'nullable', 'decimal:0,4', 'gte:0'], 'net_cash_paid' => [Rule::requiredIf($paying), 'nullable', 'decimal:0,4', 'gte:0']];
    }
}
