<?php

namespace App\Services;

use App\Models\CanvassQuotation;
use App\Models\Expense;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequest;
use App\Models\ReceivingRecord;
use App\Models\SupplierInvoice;
use App\Models\SupplierPayment;
use Illuminate\Support\Collection;

class PurchasingTraceability
{
    /** @return Collection<int, array{label: string, number: string, status: string, url: string}> */
    public function links(PurchaseRequest|CanvassQuotation|PurchaseOrder|ReceivingRecord|SupplierInvoice|SupplierPayment|Expense $record): Collection
    {
        if ($record instanceof Expense) {
            return collect([$this->link('Expense', $record->expense_number ?? 'Draft #'.$record->id, $record->status->value, route('expenses.show', $record))]);
        }
        $order = $this->orderFor($record);
        $request = $this->requestFor($record, $order);
        $links = collect();
        if ($request) {
            $links->push($this->link('Request', $request->request_number ?? 'Draft #'.$request->id, $request->status->value, route('purchase-requests.show', $request)));
            foreach ($request->canvassQuotations as $quote) {
                $links->push($this->link('Canvass', $quote->supplier_name, $quote->selected ? 'selected' : 'not_selected', route('purchase-requests.show', $request).'#canvass'));
            }
        }
        if ($order) {
            $links->push($this->link('Order', $order->purchase_order_number ?? 'Draft #'.$order->id, $order->status->value, route('purchase-orders.show', $order)));
            foreach ($order->receivingRecords as $receipt) {
                $links->push($this->link('Receipt', $receipt->receiving_number ?? 'Draft #'.$receipt->id, $receipt->status->value, route('receiving-records.show', $receipt)));
            }
            foreach ($order->supplierInvoices as $invoice) {
                $links->push($this->link('Invoice', $invoice->internal_number ?? $invoice->supplier_invoice_number, $invoice->status->value, route('supplier-invoices.show', $invoice)));
                foreach ($invoice->paymentAllocations as $allocation) {
                    $payment = SupplierPayment::query()->findOrFail($allocation->supplier_payment_id);
                    $links->push($this->link('Payment', $payment->payment_number ?? 'Draft #'.$payment->id, $payment->status->value, route('supplier-payments.show', $payment)));
                }
            }
        }

        return $links->unique('url')->values();
    }

    private function orderFor(PurchaseRequest|CanvassQuotation|PurchaseOrder|ReceivingRecord|SupplierInvoice|SupplierPayment|Expense $record): ?PurchaseOrder
    {
        return match (true) {
            $record instanceof PurchaseRequest => PurchaseOrder::query()->whereBelongsTo($record)->first(),
            $record instanceof CanvassQuotation => PurchaseOrder::query()->where('purchase_request_id', $record->purchase_request_id)->first(),
            $record instanceof PurchaseOrder => $record,
            $record instanceof ReceivingRecord, $record instanceof SupplierInvoice => PurchaseOrder::query()->find($record->purchase_order_id),
            $record instanceof SupplierPayment => PurchaseOrder::query()->whereIn('id', SupplierInvoice::query()->select('purchase_order_id')
                ->whereIn('id', $record->allocations()->select('supplier_invoice_id')))->first(),
            default => null,
        };
    }

    private function requestFor(PurchaseRequest|CanvassQuotation|PurchaseOrder|ReceivingRecord|SupplierInvoice|SupplierPayment|Expense $record, ?PurchaseOrder $order): ?PurchaseRequest
    {
        return match (true) {
            $record instanceof PurchaseRequest => $record,
            $record instanceof CanvassQuotation => PurchaseRequest::query()->find($record->purchase_request_id),
            default => $order?->purchase_request_id ? PurchaseRequest::query()->find($order->purchase_request_id) : null,
        };
    }

    /** @return array{label: string, number: string, status: string, url: string} */
    private function link(string $label, string $number, string $status, string $url): array
    {
        return compact('label', 'number', 'status', 'url');
    }
}
