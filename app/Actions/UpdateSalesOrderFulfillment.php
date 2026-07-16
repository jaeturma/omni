<?php

namespace App\Actions;

use App\Enums\SalesOrderStatus;
use App\Models\SalesOrder;
use Illuminate\Support\Facades\DB;

class UpdateSalesOrderFulfillment
{
    /** @param array<int,array{delivered_quantity:string,invoiced_quantity:string}> $quantities */
    public function handle(SalesOrder $order, array $quantities, int $userId): SalesOrder
    {
        return DB::transaction(function () use ($order, $quantities, $userId) {
            $locked = SalesOrder::with('lines')->lockForUpdate()->findOrFail($order->id);
            foreach ($locked->lines as $line) {
                $input = $quantities[$line->id];
                $line->update($input);
            } $all = $locked->lines()->get();
            $status = $all->every(fn ($l) => bccomp(bcadd($l->delivered_quantity, $l->cancelled_quantity, 4), $l->ordered_quantity, 4) === 0) ? SalesOrderStatus::Fulfilled : ($all->contains(fn ($l) => bccomp($l->delivered_quantity, '0', 4) === 1 || bccomp($l->cancelled_quantity, '0', 4) === 1) ? SalesOrderStatus::PartiallyFulfilled : SalesOrderStatus::Confirmed);
            $locked->update(['status' => $status, 'updated_by' => $userId]);

            return $locked->fresh('lines');
        });
    }
}
