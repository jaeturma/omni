<?php

namespace App\Http\Requests;

use App\Enums\ExpenseStatus;
use App\Models\Expense;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreExpenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        $expense = $this->route('expense');

        return $expense instanceof Expense ? (bool) $this->user()?->can('update', $expense) : (bool) $this->user()?->can('create', Expense::class);
    }

    public function rules(): array
    {
        return ['fiscal_period_id' => ['required', 'integer', 'exists:fiscal_periods,id'], 'supplier_id' => ['nullable', Rule::exists('suppliers', 'id')->where('status', 'active')], 'customer_id' => ['nullable', Rule::exists('customers', 'id')->where('status', 'active')], 'payment_method_id' => ['nullable', Rule::exists('payment_methods', 'id')->where('status', 'active')], 'bank_id' => ['nullable', Rule::exists('banks', 'id')->where('status', 'active')], 'expense_date' => ['required', 'date'], 'payee_name' => ['required_without:supplier_id', 'nullable', 'string', 'max:255'], 'expense_category' => ['required', Rule::in(Expense::CATEGORIES)], 'description' => ['required', 'string', 'max:255'], 'business_purpose' => ['required', 'string', 'max:2000'], 'reference_number' => ['nullable', 'string', 'max:100'], 'project_reference' => ['nullable', 'string', 'max:100'], 'receipt_available' => ['nullable', 'boolean'], 'receipt_reference' => ['nullable', 'string', 'max:100'], 'gross_amount' => ['required', 'decimal:0,4', 'gt:0'], 'withholding_amount' => ['nullable', 'decimal:0,4', 'gte:0'], 'other_deductions' => ['nullable', 'decimal:0,4', 'gte:0'], 'net_cash_paid' => ['nullable', 'decimal:0,4', 'gte:0'], 'reimbursable' => ['nullable', 'boolean'], 'notes' => ['nullable', 'string', 'max:5000']];
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            $expense = $this->route('expense');
            if ($expense instanceof Expense && $expense->status !== ExpenseStatus::Draft) {
                $validator->errors()->add('status', 'Approved, paid, reimbursable, or voided expenses cannot be edited.');
            }
        }];
    }
}
