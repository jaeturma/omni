<?php

namespace App\Actions;

use App\Models\Customer;
use App\Models\DeliveryLine;
use App\Models\SalesInvoice;
use App\Models\SalesOrderLine;
use App\Support\SalesAmountCalculator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SaveSalesInvoice
{
    public function __construct(private SalesAmountCalculator $calculator) {}

    /** @param array<string, mixed> $data */
    public function handle(array $data, int $userId, ?SalesInvoice $invoice = null): SalesInvoice
    {
        return DB::transaction(function () use ($data, $userId, $invoice) {
            $customer = Customer::findOrFail($data['customer_id']);
            $lines = [];
            foreach ($data['lines'] as $index => $input) {
                $quantity = (string) $input['quantity'];
                $source = $this->sourceLine($data['source_type'], $input);
                if ($source) {
                    $unitPrice = $source->unit_price;
                    $discountRate = $source->discount_rate;
                    $productServiceId = $source->product_service_id;
                    $itemType = $source->item_type;
                    $sku = $source->sku;
                    $description = $source->description;
                    $uomCode = $source->uom_code;
                    $uomName = $source->uom_name;
                } else {
                    $unitPrice = (string) $input['unit_price'];
                    $discountRate = (string) ($input['discount_rate'] ?? '0');
                    $productServiceId = $input['product_service_id'] ?? null;
                    $itemType = 'service';
                    $sku = $input['sku'] ?? null;
                    $description = $input['description'];
                    $uomCode = $input['uom_code'];
                    $uomName = $input['uom_name'];
                }
                $amounts = $this->calculator->line($quantity, $unitPrice, $discountRate);
                $lines[] = $amounts + [
                    'sales_order_line_id' => $source?->id,
                    'delivery_line_id' => $input['delivery_line_id'] ?? null,
                    'product_service_id' => $productServiceId,
                    'line_number' => $index + 1,
                    'item_type' => $itemType,
                    'sku' => $sku,
                    'description' => $description,
                    'uom_code' => $uomCode,
                    'uom_name' => $uomName,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'discount_rate' => $discountRate,
                ];
            }
            $totals = $this->calculator->document($lines);
            $withholding = (string) ($data['expected_withholding_amount'] ?? '0');
            if (bccomp($withholding, $totals['grand_total'], 4) === 1) {
                throw ValidationException::withMessages(['expected_withholding_amount' => 'Expected withholding cannot exceed net sales.']);
            }
            $header = [
                'sales_order_id' => $data['sales_order_id'] ?? null, 'delivery_id' => $data['delivery_id'] ?? null,
                'customer_id' => $customer->id, 'fiscal_period_id' => $data['fiscal_period_id'], 'invoice_date' => $data['invoice_date'],
                'due_date' => $data['due_date'], 'customer_name' => $customer->name, 'customer_tin' => $customer->tin,
                'billing_address' => $customer->address, 'customer_po_number' => $data['customer_po_number'] ?? null,
                'source_type' => $data['source_type'], 'gross_amount' => $totals['subtotal'],
                'discount_amount' => bcadd($totals['line_discount_total'], $totals['document_discount_amount'], 4),
                'net_sales_amount' => $totals['grand_total'], 'expected_withholding_amount' => $withholding,
                'total_receivable' => bcsub($totals['grand_total'], $withholding, 4), 'paid_amount' => '0.0000',
                'balance_due' => bcsub($totals['grand_total'], $withholding, 4), 'notes' => $data['notes'] ?? null, 'updated_by' => $userId,
            ];
            if ($invoice) {
                $invoice->update($header);
                $invoice->lines()->delete();
            } else {
                $invoice = SalesInvoice::create($header + ['created_by' => $userId]);
            }
            $invoice->lines()->createMany($lines);

            return $invoice->load('lines');
        });
    }

    /** @param array<string, mixed> $input */
    private function sourceLine(string $sourceType, array $input): ?SalesOrderLine
    {
        if ($sourceType === 'direct') {
            return null;
        }
        if ($sourceType === 'delivery') {
            return DeliveryLine::with('salesOrderLine')->findOrFail($input['delivery_line_id'])->salesOrderLine;
        }

        return SalesOrderLine::findOrFail($input['sales_order_line_id']);
    }
}
