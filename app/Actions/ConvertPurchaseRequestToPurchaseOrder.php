<?php

namespace App\Actions;

use App\Enums\PurchaseRequestStatus;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ConvertPurchaseRequestToPurchaseOrder
{
    public function __construct(private SavePurchaseOrder $saveOrder) {}

    /** @param array<string, mixed> $data */
    public function handle(PurchaseRequest $request, array $data, int $userId): PurchaseOrder
    {
        return DB::transaction(function () use ($request, $data, $userId): PurchaseOrder {
            $locked = PurchaseRequest::query()->with(['lines', 'canvassQuotations'])->lockForUpdate()->findOrFail($request->id);
            if ($locked->status !== PurchaseRequestStatus::Approved) {
                throw ValidationException::withMessages(['purchase_request' => 'Only approved purchase requests may be converted.']);
            }
            if ($locked->purchaseOrder()->exists()) {
                throw ValidationException::withMessages(['purchase_request' => 'This purchase request has already been converted.']);
            }
            $selected = $locked->canvassQuotations()->where('selected', true)->first();
            $supplierId = $selected ? $selected->supplier_id : ($data['supplier_id'] ?? null);
            if (! $supplierId) {
                throw ValidationException::withMessages(['supplier_id' => 'Select a supplier when the request has no selected canvass quotation.']);
            }
            $payload = $data + ['supplier_id' => $supplierId];
            $payload['supplier_id'] = $supplierId;
            $payload['purchase_request_id'] = $locked->id;
            $payload['canvass_quotation_id'] = $selected?->id;
            if ($selected) {
                $payload['payment_terms'] = $selected->payment_terms;
            }
            $payload['lines'] = $locked->lines->map(fn ($line) => ['purchase_request_line_id' => $line->id, 'product_service_id' => $line->product_service_id, 'description' => $line->description, 'uom_code' => $line->uom_code, 'uom_name' => $line->uom_name, 'ordered_quantity' => $line->quantity, 'unit_cost' => $line->estimated_unit_cost, 'discount_rate' => '0.000000'])->all();
            $order = $this->saveOrder->handle($payload, $userId);
            $locked->update(['status' => PurchaseRequestStatus::Converted, 'converted_at' => now(), 'converted_by' => $userId, 'updated_by' => $userId]);

            return $order;
        }, 3);
    }
}
