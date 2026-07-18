<?php

namespace App\Actions;

use App\Enums\SupplierInvoiceStatus;
use App\Enums\SupplierPaymentAllocationStatus;
use App\Enums\SupplierPaymentStatus;
use App\Models\DocumentSequence;
use App\Models\SupplierInvoice;
use App\Models\SupplierPayment;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TransitionSupplierPayment
{
    public function __construct(private IssueDocumentNumber $issueNumber) {}

    public function handle(SupplierPayment $payment, SupplierPaymentStatus $target, int $userId, ?string $reason = null): SupplierPayment
    {
        return DB::transaction(function () use ($payment, $target, $userId, $reason): SupplierPayment {
            $locked = SupplierPayment::query()->with('allocations')->lockForUpdate()->findOrFail($payment->id);
            if (! $locked->status->canTransitionTo($target)) {
                throw ValidationException::withMessages(['status' => 'This supplier-payment transition is not allowed.']);
            }
            $changes = ['status' => $target, 'updated_by' => $userId];
            if ($target === SupplierPaymentStatus::Posted) {
                $sequence = DocumentSequence::query()->where('document_type', 'supplier_payment')->where('active', true)->whereHas('fiscalYear', fn ($query) => $query->whereDate('starts_on', '<=', $locked->payment_date)->whereDate('ends_on', '>=', $locked->payment_date))->first();
                if (! $sequence) {
                    throw ValidationException::withMessages(['status' => 'Configure an active supplier-payment sequence for this payment date.']);
                }
                $reservation = $this->issueNumber->handle($sequence, $userId);
                $changes += ['payment_number' => $reservation->document_number, 'document_number_reservation_id' => $reservation->id, 'posted_at' => now(), 'posted_by' => $userId];
            }
            if ($target === SupplierPaymentStatus::Voided) {
                foreach ($locked->allocations()->where('status', SupplierPaymentAllocationStatus::Active)->lockForUpdate()->get() as $allocation) {
                    $invoice = SupplierInvoice::query()->lockForUpdate()->findOrFail($allocation->supplier_invoice_id);
                    $paid = bcsub($invoice->paid_amount, $allocation->amount, 4);
                    $balance = bcadd($invoice->balance_due, $allocation->amount, 4);
                    $status = bccomp($paid, '0', 4) === 0 ? ($invoice->due_date->isPast() ? SupplierInvoiceStatus::Overdue : SupplierInvoiceStatus::Posted) : SupplierInvoiceStatus::PartiallyPaid;
                    $invoice->update(['paid_amount' => $paid, 'balance_due' => $balance, 'status' => $status, 'updated_by' => $userId]);
                    $allocation->update(['status' => SupplierPaymentAllocationStatus::Reversed, 'reversed_at' => now(), 'reversed_by' => $userId]);
                }
                $changes += ['unapplied_amount' => $locked->gross_settlement_amount, 'voided_at' => now(), 'voided_by' => $userId, 'void_reason' => $reason];
            }
            $locked->update($changes);

            return $locked->fresh(['allocations.supplierInvoice']);
        }, 3);
    }
}
