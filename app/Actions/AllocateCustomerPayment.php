<?php

namespace App\Actions;

use App\Enums\CustomerPaymentStatus;
use App\Enums\SalesInvoiceStatus;
use App\Models\CustomerPayment;
use App\Models\SalesInvoice;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AllocateCustomerPayment
{
    /** @param list<array{sales_invoice_id: int, amount: numeric-string}> $allocations */
    public function handle(CustomerPayment $payment, array $allocations, int $userId): CustomerPayment
    {
        return DB::transaction(function () use ($payment, $allocations, $userId) {
            $lockedPayment = CustomerPayment::lockForUpdate()->findOrFail($payment->id);
            if (! in_array($lockedPayment->status, [CustomerPaymentStatus::Posted, CustomerPaymentStatus::PartiallyAllocated], true)) {
                throw ValidationException::withMessages(['allocations' => 'Only posted payments with an unapplied balance can be allocated.']);
            }

            $total = '0.0000';
            foreach ($allocations as $index => $input) {
                $invoice = SalesInvoice::lockForUpdate()->findOrFail($input['sales_invoice_id']);
                $amount = (string) $input['amount'];
                if ($invoice->customer_id !== $lockedPayment->customer_id) {
                    throw ValidationException::withMessages(["allocations.{$index}.sales_invoice_id" => 'The invoice must belong to the payment customer.']);
                }
                if (! in_array($invoice->status, [SalesInvoiceStatus::Posted, SalesInvoiceStatus::PartiallyPaid, SalesInvoiceStatus::Overdue], true)) {
                    throw ValidationException::withMessages(["allocations.{$index}.sales_invoice_id" => 'The invoice is not open for allocation.']);
                }
                if (bccomp($amount, $invoice->balance_due, 4) === 1) {
                    throw ValidationException::withMessages(["allocations.{$index}.amount" => 'The allocation exceeds the invoice balance.']);
                }
                $total = bcadd($total, $amount, 4);
                if (bccomp($total, $lockedPayment->unapplied_amount, 4) === 1) {
                    throw ValidationException::withMessages(['allocations' => 'The allocations exceed the unapplied payment amount.']);
                }

                $newPaid = bcadd($invoice->paid_amount, $amount, 4);
                $newBalance = bcsub($invoice->balance_due, $amount, 4);
                $invoice->update(['paid_amount' => $newPaid, 'balance_due' => $newBalance,
                    'status' => bccomp($newBalance, '0', 4) === 0 ? SalesInvoiceStatus::Paid : SalesInvoiceStatus::PartiallyPaid,
                    'updated_by' => $userId]);
                $lockedPayment->allocations()->create(['sales_invoice_id' => $invoice->id, 'amount' => $amount,
                    'allocated_at' => now(), 'allocated_by' => $userId]);
            }

            $unapplied = bcsub($lockedPayment->unapplied_amount, $total, 4);
            $lockedPayment->update(['unapplied_amount' => $unapplied,
                'status' => bccomp($unapplied, '0', 4) === 0 ? CustomerPaymentStatus::FullyAllocated : CustomerPaymentStatus::PartiallyAllocated,
                'updated_by' => $userId]);

            return $lockedPayment->fresh(['allocations.salesInvoice']);
        }, 3);
    }
}
