<?php

namespace App\Actions;

use App\Models\ProductService;
use App\Models\PurchaseRequest;
use App\Support\PurchasingAmountCalculator;
use Illuminate\Support\Facades\DB;

class SavePurchaseRequest
{
    public function __construct(private PurchasingAmountCalculator $calculator) {}

    /** @param array<string, mixed> $data */
    public function handle(array $data, int $userId, ?PurchaseRequest $purchaseRequest = null): PurchaseRequest
    {
        return DB::transaction(function () use ($data, $userId, $purchaseRequest): PurchaseRequest {
            $lines = [];
            $total = '0.0000';
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
                $amount = $this->calculator->line((string) $input['quantity'], (string) $input['estimated_unit_cost']);
                $total = bcadd($total, $amount['gross_amount'], 4);
                $lines[] = [
                    'product_service_id' => $item?->id,
                    'preferred_supplier_id' => $input['preferred_supplier_id'] ?? null,
                    'line_number' => $position + 1,
                    'item_type' => $itemType,
                    'sku' => $sku,
                    'description' => $input['description'],
                    'uom_code' => $uomCode,
                    'uom_name' => $uomName,
                    'quantity' => $input['quantity'],
                    'estimated_unit_cost' => $input['estimated_unit_cost'],
                    'estimated_total' => $amount['gross_amount'],
                    'notes' => $input['notes'] ?? null,
                ];
            }
            unset($data['lines']);
            $header = $data + ['estimated_total' => $total, 'created_by' => $userId, 'updated_by' => $userId];
            if ($purchaseRequest) {
                unset($header['created_by']);
                $purchaseRequest->update($header);
                $purchaseRequest->lines()->delete();
            } else {
                $purchaseRequest = PurchaseRequest::query()->create($header);
            }
            $purchaseRequest->lines()->createMany($lines);

            return $purchaseRequest->load(['requester', 'lines']);
        });
    }
}
