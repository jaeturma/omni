<?php

namespace App\Actions;

use App\Enums\SalesInvoiceStatus;
use App\Models\DocumentSequence;
use App\Models\SalesInvoice;
use App\Models\SalesInvoiceLine;
use App\Models\SalesOrderLine;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TransitionSalesInvoice
{
    public function __construct(private IssueDocumentNumber $issue) {}

    /** @param array<string, mixed> $data */
    public function handle(SalesInvoice $invoice, SalesInvoiceStatus $target, int $userId, array $data = []): SalesInvoice
    {
        return DB::transaction(function () use ($invoice, $target, $userId, $data) {
            $locked = SalesInvoice::with('lines')->lockForUpdate()->findOrFail($invoice->id);
            if (! $locked->status->canTransitionTo($target)) {
                throw ValidationException::withMessages(['status' => 'This invoice transition is not allowed.']);
            }
            $changes = ['status' => $target, 'updated_by' => $userId];
            if ($target === SalesInvoiceStatus::Posted) {
                $this->applySourceQuantities($locked, false);
                $period = $locked->fiscalPeriod;
                if ($period->status !== 'open' || ! $locked->invoice_date->betweenIncluded($period->starts_on, $period->ends_on)) {
                    throw ValidationException::withMessages(['invoice_date' => 'The invoice date must belong to an open fiscal period.']);
                }
                $sequence = DocumentSequence::where('document_type', 'sales_invoice')->where('active', true)
                    ->where('fiscal_year_id', $period->fiscal_year_id)->first();
                if (! $sequence) {
                    throw ValidationException::withMessages(['status' => 'Configure an active sales invoice sequence for this fiscal year.']);
                }
                $reservation = $this->issue->handle($sequence, $userId);
                $changes += ['invoice_number' => $reservation->document_number, 'document_number_reservation_id' => $reservation->id, 'posted_at' => now(), 'posted_by' => $userId];
            }
            if ($target === SalesInvoiceStatus::Voided) {
                if (bccomp($locked->paid_amount, '0', 4) !== 0) {
                    throw ValidationException::withMessages(['status' => 'An invoice with payments cannot be voided.']);
                }
                $this->applySourceQuantities($locked, true);
                $changes += ['voided_at' => now(), 'voided_by' => $userId, 'void_reason' => $data['reason']];
            }
            $locked->update($changes);

            return $locked->fresh('lines');
        }, 3);
    }

    private function applySourceQuantities(SalesInvoice $invoice, bool $reverse): void
    {
        foreach ($invoice->lines as $line) {
            if (! $line->sales_order_line_id) {
                continue;
            }
            $orderLine = SalesOrderLine::lockForUpdate()->findOrFail($line->sales_order_line_id);
            if (! $reverse) {
                $available = bcsub(bcsub($orderLine->ordered_quantity, $orderLine->cancelled_quantity, 4), $orderLine->invoiced_quantity, 4);
                if ($invoice->source_type === 'delivery') {
                    $deliveryQuantity = $line->deliveryLine()->value('delivered_quantity');
                    $alreadyInvoiced = SalesInvoiceLine::where('delivery_line_id', $line->delivery_line_id)
                        ->whereHas('salesInvoice', fn ($query) => $query->whereNotIn('status', [SalesInvoiceStatus::Draft, SalesInvoiceStatus::Voided]))->sum('quantity');
                    $available = bcsub((string) $deliveryQuantity, (string) $alreadyInvoiced, 4);
                }
                if (bccomp($line->quantity, $available, 4) === 1) {
                    throw ValidationException::withMessages(['lines' => 'Invoice quantity exceeds the source quantity available.']);
                }
            }
            $orderLine->update(['invoiced_quantity' => $reverse
                ? bcsub($orderLine->invoiced_quantity, $line->quantity, 4)
                : bcadd($orderLine->invoiced_quantity, $line->quantity, 4)]);
        }
    }
}
