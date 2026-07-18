<?php

namespace App\Actions;

use App\Models\ProductService;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Support\PurchasingAmountCalculator;
use Illuminate\Support\Facades\DB;

class SavePurchaseOrder
{
    public function __construct(private PurchasingAmountCalculator $calculator) {}

    /** @param array<string, mixed> $data */
    public function handle(array $data, int $userId, ?PurchaseOrder $order = null): PurchaseOrder
    {
        return DB::transaction(function () use ($data, $userId, $order): PurchaseOrder {
            $supplier = Supplier::query()->findOrFail($data['supplier_id']);
            $lines = [];
            foreach ($data['lines'] as $position => $input) {
                $item = null;
                $itemType = 'free_text';
                $sku = null;
                $uomCode = $input['uom_code'] ?? null;
                $uomName = $input['uom_name'] ?? null;
                if (isset($input['product_service_id'])) {
                    $item = ProductService::query()->with('unitOfMeasure:id,code,name')->findOrFail($input['product_service_id']);
                    $itemType = $item->type;
                    $sku = $item->sku;
                    $uomCode = $item->unitOfMeasure->code;
                    $uomName = $item->unitOfMeasure->name;
                }
                $amounts = $this->calculator->line((string) $input['ordered_quantity'], (string) $input['unit_cost'], (string) $input['discount_rate']);
                $lines[] = ['purchase_request_line_id' => $input['purchase_request_line_id'] ?? null, 'product_service_id' => $item?->id, 'line_number' => $position + 1, 'item_type' => $itemType, 'sku' => $sku, 'description' => $input['description'], 'uom_code' => $uomCode, 'uom_name' => $uomName, 'ordered_quantity' => $input['ordered_quantity'], 'received_quantity' => '0.0000', 'billed_quantity' => '0.0000', 'cancelled_quantity' => '0.0000', 'unit_cost' => $input['unit_cost'], 'discount_rate' => $input['discount_rate'], ...$amounts];
            }
            $totals = $this->calculator->document($lines, (string) $data['document_discount_rate'], (string) $data['freight']);
            $totals['grand_total'] = bcadd($totals['grand_total'], (string) $data['other_charges'], 4);
            unset($data['lines']);
            $header = $data + $totals + ['supplier_name' => $supplier->name, 'supplier_tin' => $supplier->tin, 'supplier_address' => $supplier->address, 'created_by' => $userId, 'updated_by' => $userId];
            if ($order) {
                unset($header['created_by']);
                $order->update($header);
                $order->lines()->delete();
            } else {
                $order = PurchaseOrder::query()->create($header);
            }
            $order->lines()->createMany($lines);

            return $order->load(['supplier', 'lines']);
        });
    }
}
