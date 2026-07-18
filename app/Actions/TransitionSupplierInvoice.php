<?php

namespace App\Actions;

use App\Enums\SupplierInvoiceStatus;
use App\Models\DocumentSequence;
use App\Models\FiscalPeriod;
use App\Models\PurchaseOrderLine;
use App\Models\ReceivingRecordLine;
use App\Models\SupplierInvoice;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TransitionSupplierInvoice
{
    public function __construct(private IssueDocumentNumber $issueNumber) {}

    public function handle(SupplierInvoice $invoice, SupplierInvoiceStatus $target, int $userId, ?string $reason = null): SupplierInvoice
    {
        return DB::transaction(function () use ($invoice, $target, $userId, $reason): SupplierInvoice {
            $locked = SupplierInvoice::query()->with('lines')->lockForUpdate()->findOrFail($invoice->id);
            if (! $locked->status->canTransitionTo($target)) {
                throw ValidationException::withMessages(['status' => 'This supplier-invoice transition is not allowed.']);
            }
            $changes = ['status' => $target, 'updated_by' => $userId];
            if ($target === SupplierInvoiceStatus::Posted) {
                $period = FiscalPeriod::query()->lockForUpdate()->findOrFail($locked->fiscal_period_id);
                if ($period->status !== 'open' || $locked->invoice_date->lt($period->starts_on) || $locked->invoice_date->gt($period->ends_on)) {
                    throw ValidationException::withMessages(['fiscal_period_id' => 'The invoice date must belong to an open fiscal period.']);
                }
                $this->applySourceQuantities($locked, false);
                $sequence = DocumentSequence::query()->where('document_type', 'purchase_invoice')->where('active', true)->where('fiscal_year_id', $period->fiscal_year_id)->first();
                if (! $sequence) {
                    throw ValidationException::withMessages(['status' => 'Configure an active purchase-invoice sequence for this fiscal year.']);
                }
                $reservation = $this->issueNumber->handle($sequence, $userId);
                $changes += ['internal_number' => $reservation->document_number, 'document_number_reservation_id' => $reservation->id, 'posted_at' => now(), 'posted_by' => $userId];
            }
            if ($target === SupplierInvoiceStatus::Voided) {
                $this->applySourceQuantities($locked, true);
                $changes += ['balance_due' => '0.0000', 'voided_at' => now(), 'voided_by' => $userId, 'void_reason' => $reason];
            }
            $locked->update($changes);

            return $locked->fresh(['supplier', 'fiscalPeriod', 'lines']);
        }, 3);
    }

    private function applySourceQuantities(SupplierInvoice $invoice, bool $reverse): void
    {
        foreach ($invoice->lines as $line) {
            if (! $line->purchase_order_line_id) {
                continue;
            }
            $orderLine = PurchaseOrderLine::query()->lockForUpdate()->findOrFail($line->purchase_order_line_id);
            if (! $reverse) {
                $limit = $orderLine->received_quantity;
                if ($line->receiving_record_line_id) {
                    $receiptLine = ReceivingRecordLine::query()->lockForUpdate()->findOrFail($line->receiving_record_line_id);
                    $alreadyBilled = SupplierInvoice::query()->whereIn('status', [SupplierInvoiceStatus::Posted, SupplierInvoiceStatus::PartiallyPaid, SupplierInvoiceStatus::Paid, SupplierInvoiceStatus::Overdue])->whereKeyNot($invoice->id)->whereHas('lines', fn ($query) => $query->where('receiving_record_line_id', $receiptLine->id))->with('lines')->get()->flatMap->lines->where('receiving_record_line_id', $receiptLine->id)->reduce(fn (string $carry, $sourceLine): string => bcadd($carry, $sourceLine->quantity, 4), '0.0000');
                    $limit = bcsub($receiptLine->accepted_quantity, $alreadyBilled, 4);
                } else {
                    $limit = bcsub(bcsub($orderLine->ordered_quantity, $orderLine->billed_quantity, 4), $orderLine->cancelled_quantity, 4);
                }
                if (bccomp($line->quantity, $limit, 4) === 1) {
                    throw ValidationException::withMessages(['lines' => "Invoice quantity for {$line->description} exceeds the accepted or billable quantity."]);
                }
            }
            $orderLine->update(['billed_quantity' => $reverse ? bcsub($orderLine->billed_quantity, $line->quantity, 4) : bcadd($orderLine->billed_quantity, $line->quantity, 4)]);
        }
    }
}
