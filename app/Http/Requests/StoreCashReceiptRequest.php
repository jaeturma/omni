<?php

namespace App\Http\Requests;

use App\Enums\CashReceiptSourceType;
use App\Enums\CashReceiptStatus;
use App\Models\CashReceipt;
use App\Models\CustomerPayment;
use App\Models\FiscalPeriod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreCashReceiptRequest extends FormRequest
{
    public function authorize(): bool
    {
        $receipt = $this->route('cash_receipt');

        return $receipt instanceof CashReceipt ? (bool) $this->user()?->can('update', $receipt) : (bool) $this->user()?->can('create', CashReceipt::class);
    }

    public function rules(): array
    {
        return ['receipt_date' => ['required', 'date'], 'fiscal_period_id' => ['required', 'integer', 'exists:fiscal_periods,id'],
            'financial_account_id' => ['required', Rule::exists('financial_accounts', 'id')->where('active', true)->where('allow_receipts', true)],
            'source_type' => ['required', Rule::enum(CashReceiptSourceType::class)], 'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
            'customer_payment_id' => ['nullable', 'integer', 'exists:customer_payments,id', Rule::unique('cash_receipts', 'customer_payment_id')->ignore($this->route('cash_receipt'))], 'payer_name' => ['required', 'string', 'max:255'],
            'payment_method_id' => ['required', Rule::exists('payment_methods', 'id')->where('status', 'active')],
            'reference_number' => ['nullable', 'string', 'max:255'], 'gross_receipt' => ['required', 'decimal:0,4', 'gt:0'],
            'deductions_or_fees' => ['nullable', 'decimal:0,4', 'gte:0'], 'net_amount_deposited' => ['required', 'decimal:0,4', 'gte:0'],
            'notes' => ['nullable', 'string', 'max:5000']];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            $receipt = $this->route('cash_receipt');
            if ($receipt instanceof CashReceipt && $receipt->status !== CashReceiptStatus::Draft) {
                $validator->errors()->add('status', 'Only draft receipts may be edited.');
            }
            $period = FiscalPeriod::find($this->integer('fiscal_period_id'));
            if (! $period || $period->status !== 'open' || ! $this->date('receipt_date')?->betweenIncluded($period->starts_on, $period->ends_on)) {
                $validator->errors()->add('fiscal_period_id', 'Select the open fiscal period containing the receipt date.');
            }
            $payment = $this->filled('customer_payment_id') ? CustomerPayment::find($this->integer('customer_payment_id')) : null;
            if ($this->input('source_type') === CashReceiptSourceType::CustomerPayment->value && ! $payment) {
                $validator->errors()->add('customer_payment_id', 'A customer payment is required for this source.');
            }
            if ($this->input('source_type') !== CashReceiptSourceType::CustomerPayment->value && $this->filled('customer_payment_id')) {
                $validator->errors()->add('customer_payment_id', 'Customer payment links are only allowed for customer-payment receipts.');
            }
            if ($payment && in_array($payment->status->value, ['draft', 'voided'], true)) {
                $validator->errors()->add('customer_payment_id', 'Only posted customer payments may be linked.');
            }
            if ($payment && ((int) $payment->customer_id !== $this->integer('customer_id') || bccomp($payment->net_cash_received, (string) $this->input('net_amount_deposited'), 4) !== 0)) {
                $validator->errors()->add('customer_payment_id', 'The customer and deposited amount must match the linked payment.');
            }
            if (bccomp(bcsub((string) $this->input('gross_receipt'), (string) ($this->input('deductions_or_fees') ?: '0'), 4), (string) $this->input('net_amount_deposited'), 4) !== 0) {
                $validator->errors()->add('net_amount_deposited', 'Net deposited must equal gross receipt less deductions or fees.');
            }
        }];
    }
}
