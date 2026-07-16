<?php

namespace App\Actions;

use App\Enums\QuotationStatus;
use App\Models\Quotation;
use App\Models\SalesOrder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ConvertQuotationToSalesOrder
{
    public function handle(Quotation $quotation, int $userId): SalesOrder
    {
        return DB::transaction(function () use ($quotation, $userId) {
            $source = Quotation::with(['lines', 'customer'])->lockForUpdate()->findOrFail($quotation->id);
            if ($source->status !== QuotationStatus::Approved || $source->salesOrder()->exists()) {
                throw ValidationException::withMessages(['quotation' => 'Only an approved, unconverted quotation may be converted.']);
            }$order = SalesOrder::create(['quotation_id' => $source->id, 'customer_id' => $source->customer_id, 'order_date' => now()->toDateString(), 'promised_delivery_date' => null, 'customer_po_number' => null, 'payment_terms' => $source->customer->payment_terms, 'customer_name' => $source->customer_name, 'customer_tin' => $source->customer_tin, 'billing_address' => $source->billing_address, 'delivery_address' => $source->delivery_address, 'notes' => $source->notes, 'document_discount_rate' => $source->document_discount_rate, 'subtotal' => $source->subtotal, 'line_discount_total' => $source->line_discount_total, 'document_discount_amount' => $source->document_discount_amount, 'grand_total' => $source->grand_total, 'created_by' => $userId, 'updated_by' => $userId]);
            $order->lines()->createMany($source->lines->map(fn ($line) => ['quotation_line_id' => $line->id, 'product_service_id' => $line->product_service_id, 'line_number' => $line->line_number, 'item_type' => $line->item_type, 'sku' => $line->sku, 'description' => $line->description, 'uom_code' => $line->uom_code, 'uom_name' => $line->uom_name, 'ordered_quantity' => $line->quantity, 'delivered_quantity' => '0.0000', 'invoiced_quantity' => '0.0000', 'cancelled_quantity' => '0.0000', 'unit_price' => $line->unit_price, 'discount_rate' => $line->discount_rate, 'gross_amount' => $line->gross_amount, 'discount_amount' => $line->discount_amount, 'net_amount' => $line->net_amount])->all());
            $source->update(['status' => QuotationStatus::Converted, 'converted_at' => now(), 'converted_by' => $userId, 'updated_by' => $userId]);

            return $order->load('lines');
        }, 3);
    }
}
