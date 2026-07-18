<?php

namespace App\Actions;

use App\Models\CanvassQuotation;
use App\Models\PurchaseRequest;
use App\Models\Supplier;
use Illuminate\Support\Facades\DB;

class SaveCanvassQuotation
{
    /** @param array<string, mixed> $data */
    public function handle(PurchaseRequest $request, array $data, int $userId, ?CanvassQuotation $quotation = null): CanvassQuotation
    {
        return DB::transaction(function () use ($request, $data, $userId, $quotation): CanvassQuotation {
            $supplier = Supplier::query()->findOrFail($data['supplier_id']);
            if ($data['selected'] ?? false) {
                $request->canvassQuotations()->lockForUpdate()->where('selected', true)->update(['selected' => false, 'updated_by' => $userId]);
            }
            $values = $data + ['supplier_name' => $supplier->name, 'supplier_tin' => $supplier->tin, 'supplier_address' => $supplier->address, 'created_by' => $userId, 'updated_by' => $userId];
            if ($quotation) {
                unset($values['created_by']);
                $quotation->update($values);

                return $quotation->fresh();
            }

            $created = new CanvassQuotation($values);
            $request->canvassQuotations()->save($created);

            return $created;
        }, 3);
    }
}
