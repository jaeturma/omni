<?php

namespace App\Http\Requests;

use App\Enums\CashDisbursementSourceType;
use App\Enums\CashDisbursementStatus;
use App\Models\CashDisbursement;
use App\Models\Expense;
use App\Models\FiscalPeriod;
use App\Models\SupplierPayment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreCashDisbursementRequest extends FormRequest
{
    public function authorize(): bool
    {
        $disbursement = $this->route('cash_disbursement');

        return $disbursement instanceof CashDisbursement
            ? (bool) $this->user()?->can('update', $disbursement)
            : (bool) $this->user()?->can('create', CashDisbursement::class);
    }

    public function rules(): array
    {
        return [
            'disbursement_date' => ['required', 'date'],
            'fiscal_period_id' => ['required', 'integer', 'exists:fiscal_periods,id'],
            'financial_account_id' => ['required', Rule::exists('financial_accounts', 'id')->where('active', true)->where('allow_disbursements', true)],
            'source_type' => ['required', Rule::enum(CashDisbursementSourceType::class)],
            'supplier_payment_id' => ['nullable', 'integer', 'exists:supplier_payments,id', Rule::unique('cash_disbursements', 'supplier_payment_id')->ignore($this->route('cash_disbursement'))],
            'expense_id' => ['nullable', 'integer', 'exists:expenses,id', Rule::unique('cash_disbursements', 'expense_id')->ignore($this->route('cash_disbursement'))],
            'payee' => ['required', 'string', 'max:255'],
            'payment_method_id' => ['required', Rule::exists('payment_methods', 'id')->where('status', 'active')],
            'reference_number' => ['nullable', 'string', 'max:255'],
            'gross_settlement' => ['required', 'decimal:0,4', 'gt:0'],
            'deductions_or_bank_charges' => ['nullable', 'decimal:0,4', 'gte:0'],
            'net_cash_out' => ['required', 'decimal:0,4', 'gte:0'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            $disbursement = $this->route('cash_disbursement');
            if ($disbursement instanceof CashDisbursement && $disbursement->status !== CashDisbursementStatus::Draft) {
                $validator->errors()->add('status', 'Only draft disbursements may be edited.');
            }

            $period = FiscalPeriod::find($this->integer('fiscal_period_id'));
            if (! $period || $period->status !== 'open' || ! $this->date('disbursement_date')?->betweenIncluded($period->starts_on, $period->ends_on)) {
                $validator->errors()->add('fiscal_period_id', 'Select the open fiscal period containing the disbursement date.');
            }

            $type = $this->input('source_type');
            $supplierPayment = $this->filled('supplier_payment_id') ? SupplierPayment::with('supplier')->find($this->integer('supplier_payment_id')) : null;
            $expense = $this->filled('expense_id') ? Expense::find($this->integer('expense_id')) : null;

            if ($type === CashDisbursementSourceType::SupplierPayment->value && ! $supplierPayment) {
                $validator->errors()->add('supplier_payment_id', 'A supplier payment is required for this source.');
            }
            if ($type !== CashDisbursementSourceType::SupplierPayment->value && $supplierPayment) {
                $validator->errors()->add('supplier_payment_id', 'Supplier payment links require the supplier-payment source type.');
            }
            if ($type === CashDisbursementSourceType::Expense->value && ! $expense) {
                $validator->errors()->add('expense_id', 'An operating expense is required for this source.');
            }
            if ($type !== CashDisbursementSourceType::Expense->value && $expense) {
                $validator->errors()->add('expense_id', 'Expense links require the expense source type.');
            }
            if ($supplierPayment && in_array($supplierPayment->status->value, ['draft', 'voided'], true)) {
                $validator->errors()->add('supplier_payment_id', 'Only posted supplier payments may be linked.');
            }
            if ($supplierPayment && ($supplierPayment->supplier->name !== $this->input('payee') || bccomp($supplierPayment->net_cash_paid, (string) $this->input('net_cash_out'), 4) !== 0)) {
                $validator->errors()->add('supplier_payment_id', 'The payee and cash-out amount must match the linked supplier payment.');
            }
            if ($expense && ! in_array($expense->status->value, ['approved', 'paid', 'reimbursable'], true)) {
                $validator->errors()->add('expense_id', 'Only approved operating expenses may be linked.');
            }
            if ($expense && ($expense->payee_name !== $this->input('payee') || bccomp($expense->net_cash_paid, (string) $this->input('net_cash_out'), 4) !== 0)) {
                $validator->errors()->add('expense_id', 'The payee and cash-out amount must match the linked expense.');
            }
            if (bccomp(bcsub((string) $this->input('gross_settlement'), (string) ($this->input('deductions_or_bank_charges') ?: '0'), 4), (string) $this->input('net_cash_out'), 4) !== 0) {
                $validator->errors()->add('net_cash_out', 'Net cash out must equal gross settlement less deductions or bank charges.');
            }
        }];
    }
}
