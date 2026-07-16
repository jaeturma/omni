<?php

namespace App\Actions;

use App\Models\Delivery;
use App\Models\SalesOrder;
use Illuminate\Support\Facades\DB;

class CreateDelivery
{
    /** @param array<string,mixed>$data */
    public function handle(array $data, int $userId): Delivery
    {
        return DB::transaction(function () use ($data, $userId) {
            $order = SalesOrder::with('lines')->findOrFail($data['sales_order_id']);
            $lines = [];
            foreach ($data['lines'] as $i => $input) {
                $line = $order->lines->firstWhere('id', (int) $input['sales_order_line_id']);
                $lines[] = ['sales_order_line_id' => $line->id, 'line_number' => $i + 1, 'sku' => $line->sku, 'description' => $line->description, 'uom_code' => $line->uom_code, 'uom_name' => $line->uom_name, 'delivered_quantity' => $input['delivered_quantity']];
            }unset($data['lines']);
            $delivery = Delivery::create($data + ['customer_id' => $order->customer_id, 'customer_name' => $order->customer_name, 'customer_po_number' => $order->customer_po_number, 'created_by' => $userId, 'updated_by' => $userId]);
            $delivery->lines()->createMany($lines);

            return $delivery->load('lines');
        });
    }
}
