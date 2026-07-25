<?php

namespace App\Actions;

use App\Enums\InventoryAdjustmentStatus;
use App\Enums\InventoryMovementStatus;
use App\Enums\InventoryMovementType;
use App\Models\DocumentSequence;
use App\Models\FiscalPeriod;
use App\Models\InventoryAdjustment;
use App\Models\InventoryAdjustmentLine;
use App\Models\InventoryBalance;
use App\Models\InventoryMovement;
use App\Models\ProductService;
use App\Support\InventoryWorkflow;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ManageInventoryAdjustment
{
    public function __construct(private IssueDocumentNumber $issue) {}

    /** @param array<string, mixed> $data */
    public function create(array $data, int $userId): InventoryAdjustment
    {
        return DB::transaction(function () use ($data, $userId): InventoryAdjustment {
            $adjustment = InventoryAdjustment::query()->create([
                'adjustment_date' => $data['adjustment_date'], 'fiscal_period_id' => $data['fiscal_period_id'],
                'warehouse_id' => $data['warehouse_id'], 'type' => $data['type'],
                'inventory_adjustment_reason_id' => $data['inventory_adjustment_reason_id'],
                'explanation' => $data['explanation'], 'created_by' => $userId, 'updated_by' => $userId,
            ]);
            foreach ($data['lines'] as $index => $line) {
                $cost = $data['type'] === 'in' ? (string) $line['unit_cost'] : null;
                $adjustment->lines()->create([
                    'product_service_id' => $line['product_service_id'], 'line_number' => $index + 1,
                    'quantity' => $line['quantity'], 'unit_cost' => $cost,
                    'total_cost' => $cost === null ? null : bcmul((string) $line['quantity'], $cost, 4),
                ]);
            }

            return $adjustment;
        });
    }

    public function transition(InventoryAdjustment $adjustment, InventoryAdjustmentStatus $target, int $userId, ?string $reason = null): InventoryAdjustment
    {
        return DB::transaction(function () use ($adjustment, $target, $userId, $reason): InventoryAdjustment {
            $locked = InventoryAdjustment::query()->with('lines')->lockForUpdate()->findOrFail($adjustment->id);
            if (! $locked->status->canTransitionTo($target)) {
                throw ValidationException::withMessages(['status' => 'This inventory-adjustment transition is not allowed.']);
            }
            try {
                match ($target) {
                    InventoryAdjustmentStatus::Approved => $locked->update(['status' => $target, 'approved_at' => now(), 'approved_by' => $userId, 'updated_by' => $userId]),
                    InventoryAdjustmentStatus::Posted => $this->post($locked, $userId),
                    InventoryAdjustmentStatus::Voided => $this->void($locked, $userId, (string) $reason),
                    default => null,
                };
            } catch (DomainException $exception) {
                throw ValidationException::withMessages(['lines' => $exception->getMessage()]);
            }

            return $locked->fresh(['lines.product', 'warehouse', 'fiscalPeriod', 'reason']);
        }, 3);
    }

    private function post(InventoryAdjustment $adjustment, int $userId): void
    {
        $period = FiscalPeriod::query()->lockForUpdate()->findOrFail($adjustment->fiscal_period_id);
        if ($period->status !== 'open' || ! $adjustment->adjustment_date->betweenIncluded($period->starts_on, $period->ends_on)) {
            throw ValidationException::withMessages(['fiscal_period_id' => 'The adjustment date must belong to an open fiscal period.']);
        }
        $sequence = DocumentSequence::query()->where('document_type', 'inventory_adjustment')->where('active', true)
            ->where('fiscal_year_id', $period->fiscal_year_id)->first();
        if (! $sequence) {
            throw ValidationException::withMessages(['status' => 'Configure an active inventory-adjustment sequence for this fiscal year.']);
        }
        foreach ($adjustment->lines as $line) {
            $product = ProductService::query()->lockForUpdate()->findOrFail($line->product_service_id);
            if (! InventoryWorkflow::tracks($product)) {
                throw ValidationException::withMessages(['lines' => 'Every line must remain an inventory product.']);
            }
            $balance = InventoryBalance::query()->where('product_service_id', $product->id)
                ->where('warehouse_id', $adjustment->warehouse_id)->lockForUpdate()->first();
            $balance ??= InventoryBalance::query()->create(['product_service_id' => $product->id,
                'warehouse_id' => $adjustment->warehouse_id, 'updated_by' => $userId]);
            $beforeQuantity = $balance->quantity_on_hand;
            $beforeCost = $balance->weighted_average_cost;
            if ($adjustment->type === 'out') {
                InventoryWorkflow::assertStockAvailable($beforeQuantity, $line->quantity);
                $unitCost = $beforeCost;
                $afterQuantity = bcsub($beforeQuantity, $line->quantity, 4);
                $afterCost = bccomp($afterQuantity, '0', 4) === 0 ? '0.0000' : $beforeCost;
            } else {
                $unitCost = $line->unit_cost;
                if ($unitCost === null) {
                    throw ValidationException::withMessages(['lines' => 'Every stock-in line requires a unit cost.']);
                }
                $afterQuantity = bcadd($beforeQuantity, $line->quantity, 4);
                $afterCost = bcdiv(bcadd(bcmul($beforeQuantity, $beforeCost, 4), bcmul($line->quantity, $unitCost, 4), 4), $afterQuantity, 4);
            }
            $totalCost = bcmul($line->quantity, $unitCost, 4);
            InventoryAdjustmentLine::withoutEvents(fn () => $line->update(['unit_cost' => $unitCost, 'total_cost' => $totalCost]));
            $line->movements()->create([
                'product_service_id' => $product->id, 'warehouse_id' => $adjustment->warehouse_id,
                'type' => $adjustment->type === 'in' ? InventoryMovementType::AdjustmentIn : InventoryMovementType::AdjustmentOut,
                'movement_date' => $adjustment->adjustment_date,
                'quantity' => $adjustment->type === 'in' ? $line->quantity : bcmul($line->quantity, '-1', 4),
                'unit_cost' => $unitCost, 'total_cost' => $adjustment->type === 'in' ? $totalCost : bcmul($totalCost, '-1', 4),
                'balance_quantity_before' => $beforeQuantity, 'balance_average_cost_before' => $beforeCost,
                'balance_quantity_after' => $afterQuantity, 'balance_average_cost_after' => $afterCost,
                'status' => InventoryMovementStatus::Posted, 'posted_at' => now(), 'posted_by' => $userId, 'created_by' => $userId,
            ]);
            $balance->update(['quantity_on_hand' => $afterQuantity, 'weighted_average_cost' => $afterCost, 'updated_by' => $userId]);
        }
        $reservation = $this->issue->handle($sequence, $userId);
        $adjustment->update(['document_number_reservation_id' => $reservation->id,
            'adjustment_number' => $reservation->document_number, 'status' => InventoryAdjustmentStatus::Posted,
            'posted_at' => now(), 'posted_by' => $userId, 'updated_by' => $userId]);
    }

    private function void(InventoryAdjustment $adjustment, int $userId, string $reason): void
    {
        foreach ($adjustment->lines as $line) {
            $movement = InventoryMovement::query()->whereBelongsTo($line, 'adjustmentLine')->whereNull('reversal_of_id')->lockForUpdate()->firstOrFail();
            $balance = InventoryBalance::query()->where('product_service_id', $line->product_service_id)
                ->where('warehouse_id', $adjustment->warehouse_id)->lockForUpdate()->firstOrFail();
            $beforeQuantity = $balance->quantity_on_hand;
            $beforeCost = $balance->weighted_average_cost;
            if ($line->unit_cost === null || $line->total_cost === null) {
                throw ValidationException::withMessages(['lines' => 'Posted adjustment costing details are incomplete.']);
            }
            if ($adjustment->type === 'in') {
                InventoryWorkflow::assertStockAvailable($beforeQuantity, $line->quantity);
                $afterQuantity = bcsub($beforeQuantity, $line->quantity, 4);
                $afterCost = bccomp($afterQuantity, '0', 4) === 0 ? '0.0000' : $beforeCost;
                $quantity = bcmul($line->quantity, '-1', 4);
                $totalCost = bcmul($line->total_cost, '-1', 4);
                $type = InventoryMovementType::AdjustmentOut;
            } else {
                $afterQuantity = bcadd($beforeQuantity, $line->quantity, 4);
                $afterCost = bcdiv(bcadd(bcmul($beforeQuantity, $beforeCost, 4), $line->total_cost, 4), $afterQuantity, 4);
                $quantity = $line->quantity;
                $totalCost = $line->total_cost;
                $type = InventoryMovementType::AdjustmentIn;
            }
            InventoryMovement::query()->create([
                'inventory_adjustment_line_id' => $line->id, 'reversal_of_id' => $movement->id,
                'product_service_id' => $line->product_service_id, 'warehouse_id' => $adjustment->warehouse_id,
                'type' => $type, 'movement_date' => now()->toDateString(), 'quantity' => $quantity,
                'unit_cost' => $line->unit_cost, 'total_cost' => $totalCost,
                'balance_quantity_before' => $beforeQuantity, 'balance_average_cost_before' => $beforeCost,
                'balance_quantity_after' => $afterQuantity, 'balance_average_cost_after' => $afterCost,
                'status' => InventoryMovementStatus::Posted, 'posted_at' => now(), 'posted_by' => $userId, 'created_by' => $userId,
            ]);
            $balance->update(['quantity_on_hand' => $afterQuantity, 'weighted_average_cost' => $afterCost, 'updated_by' => $userId]);
        }
        $adjustment->update(['status' => InventoryAdjustmentStatus::Voided, 'voided_at' => now(),
            'voided_by' => $userId, 'void_reason' => $reason, 'updated_by' => $userId]);
    }
}
