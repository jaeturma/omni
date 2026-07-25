<?php

namespace App\Actions;

use App\Enums\InventoryMovementStatus;
use App\Enums\InventoryMovementType;
use App\Enums\PhysicalCountStatus;
use App\Models\DocumentSequence;
use App\Models\FiscalPeriod;
use App\Models\InventoryBalance;
use App\Models\InventoryMovement;
use App\Models\PhysicalCount;
use App\Models\PhysicalCountLine;
use App\Models\ProductService;
use App\Support\InventoryWorkflow;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ManagePhysicalCount
{
    public function __construct(private IssueDocumentNumber $issue) {}

    /** @param array<string, mixed> $data */
    public function create(array $data, int $userId): PhysicalCount
    {
        return DB::transaction(function () use ($data, $userId): PhysicalCount {
            $count = PhysicalCount::query()->create([
                'count_date' => $data['count_date'], 'fiscal_period_id' => $data['fiscal_period_id'],
                'warehouse_id' => $data['warehouse_id'], 'cutoff_at' => now(),
                'blind_count' => (bool) ($data['blind_count'] ?? false), 'notes' => $data['notes'] ?? null,
                'created_by' => $userId, 'updated_by' => $userId,
            ]);
            $productIds = collect($data['product_ids'])->map(fn (mixed $id): int => (int) $id)->sort()->values();
            foreach ($productIds as $index => $productId) {
                $product = ProductService::query()->lockForUpdate()->findOrFail($productId);
                if (! InventoryWorkflow::tracks($product)) {
                    throw ValidationException::withMessages(['product_ids' => 'Every selected item must remain an inventory product.']);
                }
                $balance = InventoryBalance::query()->where('product_service_id', $productId)
                    ->where('warehouse_id', $count->warehouse_id)->lockForUpdate()->firstOrNew();
                $count->lines()->create([
                    'product_service_id' => $productId, 'line_number' => $index + 1,
                    'system_quantity_snapshot' => $balance->quantity_on_hand,
                    'unit_cost_snapshot' => $balance->weighted_average_cost,
                ]);
            }

            return $count->fresh(['lines.product', 'warehouse', 'fiscalPeriod']);
        }, 3);
    }

    /** @param array<string, mixed> $data */
    public function record(PhysicalCount $count, array $data, int $userId): PhysicalCount
    {
        return DB::transaction(function () use ($count, $data, $userId): PhysicalCount {
            $locked = PhysicalCount::query()->lockForUpdate()->findOrFail($count->id);
            if ($locked->status !== PhysicalCountStatus::Counting) {
                throw ValidationException::withMessages(['status' => 'Counts may only be recorded while counting is in progress.']);
            }
            foreach ($data['lines'] as $input) {
                $line = PhysicalCountLine::query()->whereBelongsTo($locked, 'count')->lockForUpdate()->findOrFail($input['id']);
                $variance = bcsub((string) $input['counted_quantity'], $line->system_quantity_snapshot, 4);
                $line->update([
                    'counted_quantity' => $input['counted_quantity'], 'variance_quantity' => $variance,
                    'variance_value' => bcmul($variance, $line->unit_cost_snapshot, 4),
                    'explanation' => $input['explanation'] ?? null,
                ]);
            }
            $locked->update(['counted_by' => $userId, 'updated_by' => $userId]);

            return $locked->fresh(['lines.product', 'warehouse', 'fiscalPeriod']);
        }, 3);
    }

    public function review(PhysicalCount $count, int $userId): PhysicalCount
    {
        return DB::transaction(function () use ($count, $userId): PhysicalCount {
            $locked = PhysicalCount::query()->lockForUpdate()->findOrFail($count->id);
            if ($locked->status !== PhysicalCountStatus::Submitted) {
                throw ValidationException::withMessages(['status' => 'Only submitted counts may be reviewed.']);
            }
            $locked->update(['reviewed_at' => now(), 'reviewed_by' => $userId, 'updated_by' => $userId]);

            return $locked->fresh(['lines.product', 'warehouse', 'fiscalPeriod']);
        }, 3);
    }

    public function transition(PhysicalCount $count, PhysicalCountStatus $target, int $userId, ?string $reason = null): PhysicalCount
    {
        return DB::transaction(function () use ($count, $target, $userId, $reason): PhysicalCount {
            $locked = PhysicalCount::query()->with('lines')->lockForUpdate()->findOrFail($count->id);
            if (! $locked->status->canTransitionTo($target)) {
                throw ValidationException::withMessages(['status' => 'This physical-count transition is not allowed.']);
            }
            try {
                match ($target) {
                    PhysicalCountStatus::Counting => $this->startOrRecount($locked, $userId),
                    PhysicalCountStatus::Submitted => $this->submit($locked, $userId),
                    PhysicalCountStatus::Approved => $this->approve($locked, $userId),
                    PhysicalCountStatus::Posted => $this->post($locked, $userId),
                    PhysicalCountStatus::Voided => $this->void($locked, $userId, (string) $reason),
                    default => null,
                };
            } catch (DomainException $exception) {
                throw ValidationException::withMessages(['lines' => $exception->getMessage()]);
            }

            return $locked->fresh(['lines.product', 'warehouse', 'fiscalPeriod']);
        }, 3);
    }

    private function startOrRecount(PhysicalCount $count, int $userId): void
    {
        $changes = [
            'status' => PhysicalCountStatus::Counting, 'reviewed_at' => null, 'reviewed_by' => null,
            'approved_at' => null, 'approved_by' => null, 'updated_by' => $userId,
        ];
        if ($count->status === PhysicalCountStatus::Draft) {
            $changes += ['counting_started_at' => now(), 'counting_started_by' => $userId];
        }
        $count->update($changes);
    }

    private function submit(PhysicalCount $count, int $userId): void
    {
        foreach ($count->lines as $line) {
            if ($line->counted_quantity === null || $line->variance_quantity === null || $line->variance_value === null) {
                throw ValidationException::withMessages(['lines' => 'Every product must have a recorded count before submission.']);
            }
            if (bccomp($line->variance_quantity, '0', 4) !== 0 && blank($line->explanation)) {
                throw ValidationException::withMessages(['lines' => 'Every variance requires an explanation before submission.']);
            }
        }
        $count->update([
            'status' => PhysicalCountStatus::Submitted, 'submitted_at' => now(),
            'submitted_by' => $userId, 'updated_by' => $userId,
        ]);
    }

    private function approve(PhysicalCount $count, int $userId): void
    {
        if ($count->reviewed_by === null) {
            throw ValidationException::withMessages(['status' => 'The submitted count must be reviewed before approval.']);
        }
        $count->update([
            'status' => PhysicalCountStatus::Approved, 'approved_at' => now(),
            'approved_by' => $userId, 'updated_by' => $userId,
        ]);
    }

    private function post(PhysicalCount $count, int $userId): void
    {
        $period = FiscalPeriod::query()->lockForUpdate()->findOrFail($count->fiscal_period_id);
        if ($period->status !== 'open' || ! $count->count_date->betweenIncluded($period->starts_on, $period->ends_on)) {
            throw ValidationException::withMessages(['fiscal_period_id' => 'The count date must belong to an open fiscal period.']);
        }
        $sequence = DocumentSequence::query()->where('document_type', 'inventory_physical_count')->where('active', true)
            ->where('fiscal_year_id', $period->fiscal_year_id)->first();
        if (! $sequence) {
            throw ValidationException::withMessages(['status' => 'Configure an active physical-count sequence for this fiscal year.']);
        }
        foreach ($count->lines as $line) {
            if ($line->variance_quantity === null || $line->variance_value === null
                || bccomp($line->variance_quantity, '0', 4) === 0) {
                continue;
            }
            $balance = InventoryBalance::query()->where('product_service_id', $line->product_service_id)
                ->where('warehouse_id', $count->warehouse_id)->lockForUpdate()->first();
            $balance ??= InventoryBalance::query()->create([
                'product_service_id' => $line->product_service_id, 'warehouse_id' => $count->warehouse_id,
                'updated_by' => $userId,
            ]);
            $beforeQuantity = $balance->quantity_on_hand;
            $beforeCost = $balance->weighted_average_cost;
            $gain = bccomp($line->variance_quantity, '0', 4) === 1;
            $absoluteQuantity = $gain ? $line->variance_quantity : bcmul($line->variance_quantity, '-1', 4);
            if (! $gain) {
                InventoryWorkflow::assertStockAvailable($beforeQuantity, $absoluteQuantity);
            }
            $afterQuantity = bcadd($beforeQuantity, $line->variance_quantity, 4);
            $afterCost = $gain
                ? bcdiv(bcadd(bcmul($beforeQuantity, $beforeCost, 4), $line->variance_value, 4), $afterQuantity, 4)
                : (bccomp($afterQuantity, '0', 4) === 0 ? '0.0000' : $beforeCost);
            $line->movements()->create([
                'product_service_id' => $line->product_service_id, 'warehouse_id' => $count->warehouse_id,
                'type' => $gain ? InventoryMovementType::PhysicalCountGain : InventoryMovementType::PhysicalCountLoss,
                'movement_date' => $count->count_date, 'quantity' => $line->variance_quantity,
                'unit_cost' => $line->unit_cost_snapshot, 'total_cost' => $line->variance_value,
                'balance_quantity_before' => $beforeQuantity, 'balance_average_cost_before' => $beforeCost,
                'balance_quantity_after' => $afterQuantity, 'balance_average_cost_after' => $afterCost,
                'status' => InventoryMovementStatus::Posted, 'posted_at' => now(),
                'posted_by' => $userId, 'created_by' => $userId,
            ]);
            $balance->update([
                'quantity_on_hand' => $afterQuantity, 'weighted_average_cost' => $afterCost, 'updated_by' => $userId,
            ]);
        }
        $reservation = $this->issue->handle($sequence, $userId);
        $count->update([
            'document_number_reservation_id' => $reservation->id, 'count_number' => $reservation->document_number,
            'status' => PhysicalCountStatus::Posted, 'posted_at' => now(), 'posted_by' => $userId,
            'updated_by' => $userId,
        ]);
    }

    private function void(PhysicalCount $count, int $userId, string $reason): void
    {
        if ($count->status === PhysicalCountStatus::Posted) {
            foreach ($count->lines as $line) {
                $movement = InventoryMovement::query()->whereBelongsTo($line, 'physicalCountLine')
                    ->whereNull('reversal_of_id')->lockForUpdate()->first();
                if ($movement) {
                    $this->reverseMovement($count, $line, $movement, $userId);
                }
            }
        }
        $count->update([
            'status' => PhysicalCountStatus::Voided, 'voided_at' => now(), 'voided_by' => $userId,
            'void_reason' => $reason, 'updated_by' => $userId,
        ]);
    }

    private function reverseMovement(PhysicalCount $count, PhysicalCountLine $line, InventoryMovement $movement, int $userId): void
    {
        $balance = InventoryBalance::query()->where('product_service_id', $line->product_service_id)
            ->where('warehouse_id', $count->warehouse_id)->lockForUpdate()->firstOrFail();
        $quantity = bcmul($movement->quantity, '-1', 4);
        $totalCost = bcmul($movement->total_cost, '-1', 4);
        $beforeQuantity = $balance->quantity_on_hand;
        $beforeCost = $balance->weighted_average_cost;
        $gain = bccomp($quantity, '0', 4) === 1;
        if (! $gain) {
            InventoryWorkflow::assertStockAvailable($beforeQuantity, bcmul($quantity, '-1', 4));
        }
        $afterQuantity = bcadd($beforeQuantity, $quantity, 4);
        $unchangedSincePosting = $movement->balance_quantity_after !== null
            && $movement->balance_average_cost_after !== null
            && bccomp($beforeQuantity, $movement->balance_quantity_after, 4) === 0
            && bccomp($beforeCost, $movement->balance_average_cost_after, 4) === 0;
        if ($unchangedSincePosting && $movement->balance_average_cost_before !== null) {
            $afterCost = $movement->balance_average_cost_before;
        } else {
            $afterCost = bccomp($afterQuantity, '0', 4) === 0
                ? '0.0000'
                : bcdiv(bcadd(bcmul($beforeQuantity, $beforeCost, 4), $totalCost, 4), $afterQuantity, 4);
        }
        InventoryMovement::query()->create([
            'physical_count_line_id' => $line->id, 'reversal_of_id' => $movement->id,
            'product_service_id' => $line->product_service_id, 'warehouse_id' => $count->warehouse_id,
            'type' => $gain ? InventoryMovementType::PhysicalCountGain : InventoryMovementType::PhysicalCountLoss,
            'movement_date' => now()->toDateString(), 'quantity' => $quantity,
            'unit_cost' => $line->unit_cost_snapshot, 'total_cost' => $totalCost,
            'balance_quantity_before' => $beforeQuantity, 'balance_average_cost_before' => $beforeCost,
            'balance_quantity_after' => $afterQuantity, 'balance_average_cost_after' => $afterCost,
            'status' => InventoryMovementStatus::Posted, 'posted_at' => now(),
            'posted_by' => $userId, 'created_by' => $userId,
        ]);
        $balance->update([
            'quantity_on_hand' => $afterQuantity, 'weighted_average_cost' => $afterCost, 'updated_by' => $userId,
        ]);
    }
}
