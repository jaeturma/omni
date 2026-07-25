<?php

namespace App\Actions;

use App\Enums\InventoryMovementStatus;
use App\Enums\InventoryMovementType;
use App\Enums\InventoryOpeningStatus;
use App\Models\DocumentSequence;
use App\Models\FiscalPeriod;
use App\Models\InventoryBalance;
use App\Models\InventoryMovement;
use App\Models\InventoryOpeningBalance;
use App\Models\ProductService;
use App\Support\InventoryWorkflow;
use App\Support\WeightedAverageCosting;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ManageInventoryOpeningBalance
{
    public function __construct(private IssueDocumentNumber $issue, private WeightedAverageCosting $costing) {}

    /** @param array<string, mixed> $data */
    public function create(array $data, int $userId): InventoryOpeningBalance
    {
        return DB::transaction(function () use ($data, $userId): InventoryOpeningBalance {
            $opening = InventoryOpeningBalance::query()->create([
                'opening_date' => $data['opening_date'], 'fiscal_period_id' => $data['fiscal_period_id'],
                'warehouse_id' => $data['warehouse_id'], 'reference' => $data['reference'] ?? null,
                'notes' => $data['notes'] ?? null, 'created_by' => $userId, 'updated_by' => $userId,
            ]);
            foreach ($data['lines'] as $index => $line) {
                $opening->lines()->create([
                    'product_service_id' => $line['product_service_id'], 'line_number' => $index + 1,
                    'quantity' => $line['quantity'], 'unit_cost' => $line['unit_cost'],
                    'total_cost' => bcmul((string) $line['quantity'], (string) $line['unit_cost'], InventoryWorkflow::COST_SCALE),
                ]);
            }

            return $opening;
        });
    }

    public function transition(InventoryOpeningBalance $opening, InventoryOpeningStatus $target, int $userId, ?string $reason = null): InventoryOpeningBalance
    {
        return DB::transaction(function () use ($opening, $target, $userId, $reason): InventoryOpeningBalance {
            $locked = InventoryOpeningBalance::query()->with('lines')->lockForUpdate()->findOrFail($opening->id);
            if (! $locked->status->canTransitionTo($target)) {
                throw ValidationException::withMessages(['status' => 'This opening-balance transition is not allowed.']);
            }

            match ($target) {
                InventoryOpeningStatus::Posted => $this->post($locked, $userId),
                InventoryOpeningStatus::Voided => $this->void($locked, $userId, (string) $reason),
                default => throw ValidationException::withMessages(['status' => 'This opening-balance transition is unavailable.']),
            };

            return $locked->fresh(['lines.product', 'warehouse', 'fiscalPeriod']);
        }, 3);
    }

    private function post(InventoryOpeningBalance $opening, int $userId): void
    {
        $period = FiscalPeriod::query()->lockForUpdate()->findOrFail($opening->fiscal_period_id);
        if ($period->status !== 'open' || ! $opening->opening_date->betweenIncluded($period->starts_on, $period->ends_on)) {
            throw ValidationException::withMessages(['fiscal_period_id' => 'The opening date must belong to an open fiscal period.']);
        }
        $sequence = DocumentSequence::query()->where('document_type', 'inventory_opening_balance')->where('active', true)
            ->where('fiscal_year_id', $period->fiscal_year_id)->first();
        if (! $sequence) {
            throw ValidationException::withMessages(['status' => 'Configure an active inventory opening-balance sequence for this fiscal year.']);
        }

        foreach ($opening->lines as $line) {
            $product = ProductService::query()->lockForUpdate()->findOrFail($line->product_service_id);
            if (! InventoryWorkflow::tracks($product)) {
                throw ValidationException::withMessages(['lines' => 'Every line must remain an inventory product.']);
            }
            $balance = InventoryBalance::query()->where('product_service_id', $product->id)
                ->where('warehouse_id', $opening->warehouse_id)->lockForUpdate()->first();
            if ($balance?->opening_balance_line_id) {
                throw ValidationException::withMessages(['lines' => "{$product->name} already has a controlled opening balance in this warehouse."]);
            }
            $balance ??= InventoryBalance::query()->create([
                'product_service_id' => $product->id, 'warehouse_id' => $opening->warehouse_id, 'updated_by' => $userId,
            ]);
            $cost = $this->costing->inbound(
                $balance->quantity_on_hand, $balance->weighted_average_cost, $line->quantity, $line->unit_cost
            );
            $line->movements()->create([
                'product_service_id' => $product->id, 'warehouse_id' => $opening->warehouse_id,
                'type' => InventoryMovementType::OpeningBalance, 'movement_date' => $opening->opening_date,
                'quantity' => $line->quantity, 'unit_cost' => $line->unit_cost, 'total_cost' => $line->total_cost,
                'balance_quantity_before' => $balance->quantity_on_hand, 'balance_average_cost_before' => $balance->weighted_average_cost,
                'balance_quantity_after' => $cost['quantity'], 'balance_average_cost_after' => $cost['average_cost'],
                'status' => InventoryMovementStatus::Posted, 'posted_at' => now(), 'posted_by' => $userId, 'created_by' => $userId,
            ]);
            $balance->update(['opening_balance_line_id' => $line->id, 'quantity_on_hand' => $cost['quantity'],
                'weighted_average_cost' => $cost['average_cost'], 'updated_by' => $userId]);
        }

        $reservation = $this->issue->handle($sequence, $userId);
        $opening->update(['document_number_reservation_id' => $reservation->id, 'batch_number' => $reservation->document_number,
            'status' => InventoryOpeningStatus::Posted, 'posted_at' => now(), 'posted_by' => $userId, 'updated_by' => $userId]);
    }

    private function void(InventoryOpeningBalance $opening, int $userId, string $reason): void
    {
        foreach ($opening->lines as $line) {
            $movement = InventoryMovement::query()->whereBelongsTo($line, 'openingBalanceLine')->whereNull('reversal_of_id')->lockForUpdate()->firstOrFail();
            $balance = InventoryBalance::query()->where('product_service_id', $line->product_service_id)
                ->where('warehouse_id', $opening->warehouse_id)->lockForUpdate()->firstOrFail();
            $cost = $this->costing->removeInbound(
                $balance->quantity_on_hand, $balance->weighted_average_cost, $line->quantity, $line->unit_cost
            );
            InventoryMovement::query()->create([
                'inventory_opening_balance_line_id' => $line->id, 'reversal_of_id' => $movement->id,
                'product_service_id' => $line->product_service_id, 'warehouse_id' => $opening->warehouse_id,
                'type' => InventoryMovementType::OpeningBalance, 'movement_date' => now()->toDateString(),
                'quantity' => bcmul($line->quantity, '-1', 4), 'unit_cost' => $line->unit_cost,
                'total_cost' => bcmul($line->total_cost, '-1', 4),
                'balance_quantity_before' => $balance->quantity_on_hand, 'balance_average_cost_before' => $balance->weighted_average_cost,
                'balance_quantity_after' => $cost['quantity'], 'balance_average_cost_after' => $cost['average_cost'],
                'status' => InventoryMovementStatus::Posted,
                'posted_at' => now(), 'posted_by' => $userId, 'created_by' => $userId,
            ]);
            $balance->update(['quantity_on_hand' => $cost['quantity'],
                'weighted_average_cost' => $cost['average_cost'], 'updated_by' => $userId]);
        }
        $opening->update(['status' => InventoryOpeningStatus::Voided, 'voided_at' => now(), 'voided_by' => $userId,
            'void_reason' => $reason, 'updated_by' => $userId]);
    }
}
