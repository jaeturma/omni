<?php

namespace App\Actions;

use App\Enums\CustomerPaymentStatus;
use App\Enums\PaymentAllocationStatus;
use App\Enums\SalesInvoiceStatus;
use App\Models\CustomerPayment;
use App\Models\DocumentSequence;
use App\Models\SalesInvoice;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TransitionCustomerPayment
{
    public function __construct(private IssueDocumentNumber $issue) {}

    /** @param array<string, mixed> $data */
    public function handle(CustomerPayment $payment, CustomerPaymentStatus $target, int $userId, array $data = []): CustomerPayment
    {
        return DB::transaction(function () use ($payment, $target, $userId, $data) {
            $locked = CustomerPayment::with('allocations')->lockForUpdate()->findOrFail($payment->id);
            if (! $locked->status->canTransitionTo($target)) {
                throw ValidationException::withMessages(['status' => 'This payment transition is not allowed.']);
            }
            $changes = ['status' => $target, 'updated_by' => $userId];
            if ($target === CustomerPaymentStatus::Posted) {
                $sequence = DocumentSequence::where('document_type', 'collection_receipt')->where('active', true)
                    ->whereHas('fiscalYear', fn ($query) => $query->whereDate('starts_on', '<=', $locked->payment_date)->whereDate('ends_on', '>=', $locked->payment_date))->first();
                if (! $sequence) {
                    throw ValidationException::withMessages(['status' => 'Configure an active collection receipt sequence for this fiscal year.']);
                }
                $reservation = $this->issue->handle($sequence, $userId);
                $changes += ['payment_number' => $reservation->document_number, 'document_number_reservation_id' => $reservation->id, 'posted_at' => now(), 'posted_by' => $userId];
            }
            if ($target === CustomerPaymentStatus::Voided) {
                foreach ($locked->allocations()->where('status', PaymentAllocationStatus::Active)->lockForUpdate()->get() as $allocation) {
                    $invoice = SalesInvoice::lockForUpdate()->findOrFail($allocation->sales_invoice_id);
                    $paid = bcsub($invoice->paid_amount, $allocation->amount, 4);
                    $balance = bcadd($invoice->balance_due, $allocation->amount, 4);
                    $status = bccomp($paid, '0', 4) === 0
                        ? ($invoice->due_date->isPast() ? SalesInvoiceStatus::Overdue : SalesInvoiceStatus::Posted)
                        : SalesInvoiceStatus::PartiallyPaid;
                    $invoice->update(['paid_amount' => $paid, 'balance_due' => $balance, 'status' => $status, 'updated_by' => $userId]);
                    $allocation->update(['status' => PaymentAllocationStatus::Reversed, 'reversed_at' => now(), 'reversed_by' => $userId]);
                }
                $changes += ['unapplied_amount' => $locked->gross_settlement_amount, 'voided_at' => now(), 'voided_by' => $userId, 'void_reason' => $data['reason']];
            }
            $locked->update($changes);

            return $locked->fresh(['allocations.salesInvoice']);
        }, 3);
    }
}
