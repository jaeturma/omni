<?php

namespace App\Actions;

use App\Models\Customer;
use App\Models\ProductService;
use App\Models\SalesOrder;
use App\Support\SalesAmountCalculator;
use Illuminate\Support\Facades\DB;

class SaveSalesOrder
{
    public function __construct(private SalesAmountCalculator $calculator) {}

    /** @param array<string,mixed> $data */
    public function handle(array $data, int $userId, ?SalesOrder $order = null): SalesOrder
    {
        return DB::transaction(function () use ($data, $userId, $order) {
            $customer = Customer::findOrFail($data['customer_id']);
            $lines = [];
            foreach ($data['lines'] as $i => $input) {
                $item = ProductService::with('unitOfMeasure:id,code,name')->findOrFail($input['product_service_id']);
                $amounts = $this->calculator->line((string) $input['ordered_quantity'], (string) $input['unit_price'], (string) $input['discount_rate']);
                $lines[] = ['product_service_id' => $item->id, 'line_number' => $i + 1, 'item_type' => $item->type, 'sku' => $item->sku, 'description' => $input['description'], 'uom_code' => $item->unitOfMeasure->code, 'uom_name' => $item->unitOfMeasure->name, 'ordered_quantity' => $input['ordered_quantity'], 'delivered_quantity' => '0.0000', 'invoiced_quantity' => '0.0000', 'cancelled_quantity' => '0.0000', 'unit_price' => $input['unit_price'], 'discount_rate' => $input['discount_rate'], ...$amounts];
            }$totals = $this->calculator->document($lines, (string) $data['document_discount_rate']);
            unset($data['lines']);
            $header = $data + $totals + ['customer_name' => $customer->name, 'customer_tin' => $customer->tin, 'created_by' => $userId, 'updated_by' => $userId];
            if ($order) {
                unset($header['created_by']);
                $order->update($header);
                $order->lines()->delete();
            } else {
                $order = SalesOrder::create($header);
            }$order->lines()->createMany($lines);

            return $order->load('lines');
        });
    }
}
