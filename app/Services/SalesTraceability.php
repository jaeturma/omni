<?php

namespace App\Services;

use App\Models\CustomerPayment;
use App\Models\Delivery;
use App\Models\GovernmentDeduction;
use App\Models\Quotation;
use App\Models\SalesInvoice;
use App\Models\SalesOrder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class SalesTraceability
{
    /** @return Collection<int, array{label: string, number: string, status: string, url: string}> */
    public function links(Model $record): Collection
    {
        $quotation = $record instanceof Quotation ? $record : $this->quotationFor($record);
        $order = $record instanceof SalesOrder ? $record : ($record instanceof Quotation
            ? SalesOrder::query()->where('quotation_id', $record->id)->first()
            : $this->orderFor($record));

        $records = $this->records()->push($quotation, $order);
        if ($order) {
            $order->loadMissing(['quotation', 'deliveries', 'salesInvoices.paymentAllocations.customerPayment', 'salesInvoices.governmentDeductions']);
            $records = $records->concat($order->deliveries)->concat($order->salesInvoices);
            $order->salesInvoices->each(function (SalesInvoice $invoice) use (&$records): void {
                $records = $records->concat($invoice->paymentAllocations->pluck('customerPayment'))->concat($invoice->governmentDeductions);
            });
        }

        if ($record instanceof CustomerPayment) {
            $records = $records->push($record)->concat($record->allocations->pluck('salesInvoice'))->concat(
                GovernmentDeduction::query()->where('customer_payment_id', $record->id)->get()
            );
        }

        return $records->filter()->push($record)->unique(fn (Model $item): string => $item::class.':'.$item->getKey())
            ->map(fn (Model $item): array => $this->link($item))->values();
    }

    /** @return Collection<int, Model|null> */
    private function records(): Collection
    {
        return collect();
    }

    private function quotationFor(Model $record): ?Quotation
    {
        $quotationId = $this->orderFor($record)?->getAttribute('quotation_id');

        return is_int($quotationId) ? Quotation::query()->find($quotationId) : null;
    }

    private function orderFor(Model $record): ?SalesOrder
    {
        return match (true) {
            $record instanceof Delivery, $record instanceof SalesInvoice => $this->findOrder($record->getAttribute('sales_order_id')),
            $record instanceof GovernmentDeduction => $this->orderForInvoiceId($record->getAttribute('sales_invoice_id')),
            $record instanceof CustomerPayment => $this->orderForInvoiceId($record->allocations()->value('sales_invoice_id')),
            default => null,
        };
    }

    private function orderForInvoiceId(mixed $invoiceId): ?SalesOrder
    {
        $orderId = is_int($invoiceId) ? SalesInvoice::query()->whereKey($invoiceId)->value('sales_order_id') : null;

        return $this->findOrder($orderId);
    }

    private function findOrder(mixed $orderId): ?SalesOrder
    {
        return is_int($orderId) ? SalesOrder::query()->find($orderId) : null;
    }

    /** @return array{label: string, number: string, status: string, url: string} */
    private function link(Model $record): array
    {
        return match (true) {
            $record instanceof Quotation => ['label' => 'Quotation', 'number' => $record->quotation_number, 'status' => $record->status->value, 'url' => route('quotations.show', $record)],
            $record instanceof SalesOrder => ['label' => 'Sales order', 'number' => $record->sales_order_number, 'status' => $record->status->value, 'url' => route('sales-orders.show', $record)],
            $record instanceof Delivery => ['label' => 'Delivery', 'number' => $record->delivery_number, 'status' => $record->status->value, 'url' => route('deliveries.show', $record)],
            $record instanceof SalesInvoice => ['label' => 'Invoice', 'number' => $record->invoice_number, 'status' => $record->status->value, 'url' => route('sales-invoices.show', $record)],
            $record instanceof CustomerPayment => ['label' => 'Payment', 'number' => $record->payment_number, 'status' => $record->status->value, 'url' => route('customer-payments.show', $record)],
            $record instanceof GovernmentDeduction => ['label' => 'Government deduction', 'number' => $record->certificate_number ?: '#'.$record->id, 'status' => $record->status->value, 'url' => route('government-deductions.show', $record)],
            default => throw new \LogicException('Unsupported sales traceability record.'),
        };
    }
}
