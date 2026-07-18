<?php

namespace App\Actions;

use App\Enums\ReceivingStatus;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\ReceivingRecord;
use App\Models\ReceivingRecordLine;
use App\Models\Supplier;
use App\Models\SupplierInvoice;
use App\Support\PurchasingAmountCalculator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SaveSupplierInvoice
{
    public function __construct(private PurchasingAmountCalculator $calculator) {}

    /** @param array<string, mixed> $data */
    public function handle(array $data, int $userId, ?SupplierInvoice $invoice = null): SupplierInvoice
    {
        return DB::transaction(function () use ($data, $userId, $invoice): SupplierInvoice {
            $supplier = Supplier::query()->findOrFail($data['supplier_id']);
            $lines = [];
            foreach ($data['lines'] as $position => $input) {
                $quantity = (string) $input['quantity'];
                if (bccomp($quantity, '0', 4) === 0) {
                    continue;
                }
                $source = $this->sourceLine($input, (int) $supplier->id);
                if (! $source && ! in_array($input['item_type'], ['service', 'expense'], true)) {
                    throw ValidationException::withMessages(['lines' => 'Direct invoice lines must be services or expenses.']);
                }
                $amounts = $this->calculator->line($quantity, (string) $input['unit_cost'], (string) ($input['discount_rate'] ?? '0'));
                $lines[] = $amounts + [
                    'purchase_order_line_id' => $source['purchase_order_line_id'] ?? null,
                    'receiving_record_line_id' => $source['receiving_record_line_id'] ?? null,
                    'line_number' => $position + 1, 'item_type' => $input['item_type'], 'sku' => $input['sku'] ?? null,
                    'description' => $input['description'], 'uom_code' => $input['uom_code'], 'uom_name' => $input['uom_name'],
                    'quantity' => $quantity, 'unit_cost' => (string) $input['unit_cost'], 'discount_rate' => (string) ($input['discount_rate'] ?? '0'), 'notes' => $input['notes'] ?? null,
                ];
            }
            if ($lines === []) {
                throw ValidationException::withMessages(['lines' => 'At least one positive invoice line is required.']);
            }
            $document = $this->calculator->document($lines);
            $gross = $document['subtotal'];
            $discount = $document['line_discount_total'];
            $net = bcsub($gross, $discount, 4);
            $freight = (string) ($data['freight_amount'] ?? '0');
            $other = (string) ($data['other_charges_amount'] ?? '0');
            $withholding = (string) ($data['withholding_expected_amount'] ?? '0');
            $totalPayable = bcsub(bcadd(bcadd($net, $freight, 4), $other, 4), $withholding, 4);
            if (bccomp($totalPayable, '0', 4) === -1) {
                throw ValidationException::withMessages(['withholding_expected_amount' => 'Expected withholding cannot exceed the purchase amount and charges.']);
            }
            $header = [
                'supplier_id' => $supplier->id, 'purchase_order_id' => $data['purchase_order_id'] ?? null, 'receiving_record_id' => $data['receiving_record_id'] ?? null,
                'fiscal_period_id' => $data['fiscal_period_id'], 'supplier_invoice_number' => $data['supplier_invoice_number'], 'invoice_date' => $data['invoice_date'], 'due_date' => $data['due_date'],
                'supplier_name' => $supplier->name, 'supplier_tin' => $supplier->tin, 'supplier_address' => $supplier->address,
                'gross_purchase_amount' => $gross, 'discount_amount' => $discount, 'net_purchase_amount' => $net, 'freight_amount' => $freight,
                'other_charges_amount' => $other, 'withholding_expected_amount' => $withholding, 'total_payable' => $totalPayable,
                'paid_amount' => '0.0000', 'balance_due' => $totalPayable, 'notes' => $data['notes'] ?? null, 'updated_by' => $userId,
            ];
            if ($invoice) {
                $invoice->update($header);
                $invoice->lines()->delete();
            } else {
                $invoice = SupplierInvoice::query()->create($header + ['created_by' => $userId]);
            }
            $invoice->lines()->createMany($lines);

            return $invoice->fresh(['supplier', 'lines']);
        });
    }

    /** @param array<string, mixed> $input @return array{purchase_order_line_id?: int, receiving_record_line_id?: int}|null */
    private function sourceLine(array $input, int $supplierId): ?array
    {
        if (! empty($input['receiving_record_line_id'])) {
            $line = ReceivingRecordLine::query()->findOrFail($input['receiving_record_line_id']);
            $record = ReceivingRecord::query()->findOrFail($line->receiving_record_id);
            if ($record->supplier_id !== $supplierId || ! in_array($record->status, [ReceivingStatus::Accepted, ReceivingStatus::PartiallyAccepted], true)) {
                throw ValidationException::withMessages(['lines' => 'Receiving sources must be accepted and belong to the invoice supplier.']);
            }

            return ['purchase_order_line_id' => $line->purchase_order_line_id, 'receiving_record_line_id' => $line->id];
        }
        if (! empty($input['purchase_order_line_id'])) {
            $line = PurchaseOrderLine::query()->findOrFail($input['purchase_order_line_id']);
            if (PurchaseOrder::query()->findOrFail($line->purchase_order_id)->supplier_id !== $supplierId) {
                throw ValidationException::withMessages(['lines' => 'Purchase-order sources must belong to the invoice supplier.']);
            }

            return ['purchase_order_line_id' => $line->id];
        }

        return null;
    }
}
