<?php

namespace App\Actions;

use App\Enums\SupplierInvoiceStatus;
use App\Enums\SupplierPaymentStatus;
use App\Models\SupplierInvoice;
use App\Models\SupplierPayment;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AllocateSupplierPayment
{
    /** @param list<array{supplier_invoice_id: int, amount: numeric-string}> $allocations */
    public function handle(SupplierPayment $payment, array $allocations, int $userId): SupplierPayment
    {
        return DB::transaction(function () use ($payment, $allocations, $userId): SupplierPayment {
            $lockedPayment = SupplierPayment::query()->lockForUpdate()->findOrFail($payment->id);
            if (! in_array($lockedPayment->status, [SupplierPaymentStatus::Posted, SupplierPaymentStatus::PartiallyAllocated], true)) {
                throw ValidationException::withMessages(['allocations' => 'Only posted supplier payments with an unapplied balance can be allocated.']);
            }
            $total = '0.0000';
            foreach ($allocations as $index => $input) {
                $invoice = SupplierInvoice::query()->lockForUpdate()->findOrFail($input['supplier_invoice_id']);
                $amount = (string) $input['amount'];
                if ($invoice->supplier_id !== $lockedPayment->supplier_id) {
                    throw ValidationException::withMessages(["allocations.{$index}.supplier_invoice_id" => 'The invoice must belong to the payment supplier.']);
                }
                if (! in_array($invoice->status, [SupplierInvoiceStatus::Posted, SupplierInvoiceStatus::PartiallyPaid, SupplierInvoiceStatus::Overdue], true)) {
                    throw ValidationException::withMessages(["allocations.{$index}.supplier_invoice_id" => 'The invoice is not open for allocation.']);
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
                $invoice->update(['paid_amount' => $newPaid, 'balance_due' => $newBalance, 'status' => bccomp($newBalance, '0', 4) === 0 ? SupplierInvoiceStatus::Paid : SupplierInvoiceStatus::PartiallyPaid, 'updated_by' => $userId]);
                $lockedPayment->allocations()->create(['supplier_invoice_id' => $invoice->id, 'amount' => $amount, 'allocated_at' => now(), 'allocated_by' => $userId]);
            }
            $unapplied = bcsub($lockedPayment->unapplied_amount, $total, 4);
            $lockedPayment->update(['unapplied_amount' => $unapplied, 'status' => bccomp($unapplied, '0', 4) === 0 ? SupplierPaymentStatus::FullyAllocated : SupplierPaymentStatus::PartiallyAllocated, 'updated_by' => $userId]);

            return $lockedPayment->fresh(['allocations.supplierInvoice']);
        }, 3);
    }
}
