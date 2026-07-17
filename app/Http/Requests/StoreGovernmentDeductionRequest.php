<?php

namespace App\Http\Requests;

use App\Enums\CustomerPaymentStatus;
use App\Enums\GovernmentDeductionStatus;
use App\Enums\SalesInvoiceStatus;
use App\Models\CustomerPayment;
use App\Models\GovernmentDeduction;
use App\Models\SalesInvoice;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreGovernmentDeductionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $deduction = $this->route('government_deduction');

        return $deduction instanceof GovernmentDeduction
            ? (bool) $this->user()?->can('update', $deduction)
            : (bool) $this->user()?->can('create', GovernmentDeduction::class);
    }

    public function rules(): array
    {
        return ['sales_invoice_id' => ['required', 'integer', 'exists:sales_invoices,id'],
            'customer_payment_id' => ['nullable', 'integer', 'exists:customer_payments,id'],
            'tax_rate_setting_id' => ['required', 'integer', 'exists:tax_rate_settings,id'],
            'deduction_type' => ['required', Rule::in(GovernmentDeduction::DEDUCTION_TYPES)],
            'certificate_type' => ['required', Rule::in(GovernmentDeduction::CERTIFICATE_TYPES)],
            'certificate_number' => ['nullable', 'string', 'max:255', 'required_with:certificate_date'],
            'certificate_date' => ['nullable', 'date', 'required_with:certificate_number'],
            'covered_from' => ['required', 'date'], 'covered_to' => ['required', 'date', 'after_or_equal:covered_from'],
            'gross_basis' => ['required', 'decimal:0,4', 'gt:0'], 'notes' => ['nullable', 'string', 'max:5000'],
            'attachment_reference' => ['nullable', 'string', 'max:255']];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }
            $invoice = SalesInvoice::with('customer')->find($this->integer('sales_invoice_id'));
            if (! $invoice || $invoice->customer->type !== 'government' || ! in_array($invoice->status, [SalesInvoiceStatus::Posted, SalesInvoiceStatus::PartiallyPaid, SalesInvoiceStatus::Paid, SalesInvoiceStatus::Overdue], true)) {
                $validator->errors()->add('sales_invoice_id', 'Select a posted invoice for a government customer.');

                return;
            }
            if (bccomp((string) $this->input('gross_basis'), (string) $invoice->gross_amount, 4) === 1) {
                $validator->errors()->add('gross_basis', 'Gross basis cannot exceed the original invoice gross amount.');
            }
            if ($paymentId = $this->integer('customer_payment_id')) {
                $payment = CustomerPayment::find($paymentId);
                if (! $payment || $payment->customer_id !== $invoice->customer_id || in_array($payment->status, [CustomerPaymentStatus::Draft, CustomerPaymentStatus::Voided], true)) {
                    $validator->errors()->add('customer_payment_id', 'Select a posted payment for the same customer.');
                }
            }
            $current = $this->route('government_deduction');
            $duplicates = GovernmentDeduction::where('sales_invoice_id', $invoice->id)->where('deduction_type', $this->input('deduction_type'))
                ->whereDate('covered_from', $this->input('covered_from'))->whereDate('covered_to', $this->input('covered_to'))
                ->where('status', '!=', GovernmentDeductionStatus::Voided)->when($current instanceof GovernmentDeduction, fn ($query) => $query->whereKeyNot($current->id));
            if ($duplicates->exists()) {
                $validator->errors()->add('deduction_type', 'A matching non-voided deduction already exists for this invoice and covered period.');
            }
            if ($number = $this->input('certificate_number')) {
                $certificateExists = GovernmentDeduction::where('certificate_type', $this->input('certificate_type'))->where('certificate_number', $number)
                    ->where('status', '!=', GovernmentDeductionStatus::Voided)->when($current instanceof GovernmentDeduction, fn ($query) => $query->whereKeyNot($current->id))->exists();
                if ($certificateExists) {
                    $validator->errors()->add('certificate_number', 'This certificate is already recorded.');
                }
            }
        }];
    }
}
