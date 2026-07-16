<?php

namespace App\Actions;

use App\Models\Customer;
use App\Models\ProductService;
use App\Models\Quotation;
use App\Support\SalesAmountCalculator;
use Illuminate\Support\Facades\DB;

class SaveQuotation
{
    public function __construct(private SalesAmountCalculator $calculator) {}

    /** @param array<string, mixed> $data */
    public function handle(array $data, int $userId, ?Quotation $quotation = null): Quotation
    {
        return DB::transaction(function () use ($data, $userId, $quotation): Quotation {
            $customer = Customer::query()->findOrFail($data['customer_id']);
            $calculatedLines = [];
            foreach ($data['lines'] as $position => $input) {
                $item = ProductService::query()->with('unitOfMeasure:id,code,name')->findOrFail($input['product_service_id']);
                $amounts = $this->calculator->line((string) $input['quantity'], (string) $input['unit_price'], (string) $input['discount_rate']);
                $calculatedLines[] = [
                    'product_service_id' => $item->id, 'line_number' => $position + 1, 'item_type' => $item->type,
                    'sku' => $item->sku, 'description' => $input['description'],
                    'uom_code' => $item->unitOfMeasure->code, 'uom_name' => $item->unitOfMeasure->name,
                    'quantity' => $input['quantity'], 'unit_price' => $input['unit_price'], 'discount_rate' => $input['discount_rate'],
                    ...$amounts,
                ];
            }
            $totals = $this->calculator->document($calculatedLines, (string) $data['document_discount_rate']);
            unset($data['lines']);
            $header = $data + $totals + [
                'customer_name' => $customer->name, 'customer_tin' => $customer->tin,
                'created_by' => $userId, 'updated_by' => $userId,
            ];

            if ($quotation) {
                unset($header['created_by']);
                $quotation->update($header);
                $quotation->lines()->delete();
            } else {
                $quotation = Quotation::query()->create($header);
            }
            $quotation->lines()->createMany($calculatedLines);

            return $quotation->load(['customer', 'lines']);
        });
    }
}
