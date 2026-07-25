<?php

namespace App\Actions;

use App\Enums\InventoryMovementStatus;
use App\Enums\InventoryMovementType;
use App\Enums\InventoryTransferStatus;
use App\Models\DocumentSequence;
use App\Models\FiscalPeriod;
use App\Models\InventoryBalance;
use App\Models\InventoryMovement;
use App\Models\InventoryTransfer;
use App\Models\InventoryTransferLine;
use App\Models\ProductService;
use App\Support\InventoryWorkflow;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ManageInventoryTransfer
{
    public function __construct(private IssueDocumentNumber $issue) {}

    /** @param array<string, mixed> $data */
    public function create(array $data, int $userId): InventoryTransfer
    {
        return DB::transaction(function () use ($data, $userId): InventoryTransfer {
            $transfer = InventoryTransfer::query()->create([
                'transfer_date' => $data['transfer_date'], 'fiscal_period_id' => $data['fiscal_period_id'],
                'source_warehouse_id' => $data['source_warehouse_id'],
                'destination_warehouse_id' => $data['destination_warehouse_id'],
                'reference' => $data['reference'] ?? null, 'notes' => $data['notes'] ?? null,
                'created_by' => $userId, 'updated_by' => $userId,
            ]);
            foreach ($data['lines'] as $index => $line) {
                $transfer->lines()->create(['product_service_id' => $line['product_service_id'],
                    'line_number' => $index + 1, 'quantity' => $line['quantity']]);
            }

            return $transfer;
        });
    }

    public function transition(InventoryTransfer $transfer, InventoryTransferStatus $target, int $userId, ?string $reason = null): InventoryTransfer
    {
        return DB::transaction(function () use ($transfer, $target, $userId, $reason): InventoryTransfer {
            $locked = InventoryTransfer::query()->with('lines')->lockForUpdate()->findOrFail($transfer->id);
            if (! $locked->status->canTransitionTo($target)) {
                throw ValidationException::withMessages(['status' => 'This inventory-transfer transition is not allowed.']);
            }
            try {
                match ($target) {
                    InventoryTransferStatus::Approved => $locked->update(['status' => $target, 'approved_at' => now(), 'approved_by' => $userId, 'updated_by' => $userId]),
                    InventoryTransferStatus::Released => $this->release($locked, $userId),
                    InventoryTransferStatus::InTransit => $locked->update(['status' => $target, 'in_transit_at' => now(), 'in_transit_by' => $userId, 'updated_by' => $userId]),
                    InventoryTransferStatus::Received => $this->receive($locked, $userId),
                    InventoryTransferStatus::Completed => $locked->update(['status' => $target, 'completed_at' => now(), 'completed_by' => $userId, 'updated_by' => $userId]),
                    InventoryTransferStatus::Voided => $this->void($locked, $userId, (string) $reason),
                    default => null,
                };
            } catch (DomainException $exception) {
                throw ValidationException::withMessages(['lines' => $exception->getMessage()]);
            }

            return $locked->fresh(['lines.product', 'sourceWarehouse', 'destinationWarehouse', 'fiscalPeriod']);
        }, 3);
    }

    private function release(InventoryTransfer $transfer, int $userId): void
    {
        $period = FiscalPeriod::query()->lockForUpdate()->findOrFail($transfer->fiscal_period_id);
        if ($period->status !== 'open' || ! $transfer->transfer_date->betweenIncluded($period->starts_on, $period->ends_on)) {
            throw ValidationException::withMessages(['fiscal_period_id' => 'The transfer date must belong to an open fiscal period.']);
        }
        $sequence = DocumentSequence::query()->where('document_type', 'inventory_transfer')->where('active', true)
            ->where('fiscal_year_id', $period->fiscal_year_id)->first();
        if (! $sequence) {
            throw ValidationException::withMessages(['status' => 'Configure an active inventory-transfer sequence for this fiscal year.']);
        }
        foreach ($transfer->lines as $line) {
            $product = ProductService::query()->lockForUpdate()->findOrFail($line->product_service_id);
            if (! InventoryWorkflow::tracks($product)) {
                throw ValidationException::withMessages(['lines' => 'Every line must remain an inventory product.']);
            }
            $balance = InventoryBalance::query()->where('product_service_id', $product->id)
                ->where('warehouse_id', $transfer->source_warehouse_id)->lockForUpdate()->first();
            if (! $balance) {
                throw ValidationException::withMessages(['lines' => "{$product->name} has no stock in the source warehouse."]);
            }
            InventoryWorkflow::assertStockAvailable($balance->quantity_on_hand, $line->quantity);
            $afterQuantity = bcsub($balance->quantity_on_hand, $line->quantity, 4);
            $afterCost = bccomp($afterQuantity, '0', 4) === 0 ? '0.0000' : $balance->weighted_average_cost;
            $totalCost = bcmul($line->quantity, $balance->weighted_average_cost, 4);
            InventoryTransferLine::withoutEvents(fn () => $line->update([
                'source_unit_cost' => $balance->weighted_average_cost, 'total_cost' => $totalCost]));
            $line->movements()->create(['product_service_id' => $product->id,
                'warehouse_id' => $transfer->source_warehouse_id, 'type' => InventoryMovementType::TransferOut,
                'movement_date' => $transfer->transfer_date, 'quantity' => bcmul($line->quantity, '-1', 4),
                'unit_cost' => $balance->weighted_average_cost, 'total_cost' => bcmul($totalCost, '-1', 4),
                'balance_quantity_before' => $balance->quantity_on_hand,
                'balance_average_cost_before' => $balance->weighted_average_cost,
                'balance_quantity_after' => $afterQuantity, 'balance_average_cost_after' => $afterCost,
                'status' => InventoryMovementStatus::Posted, 'posted_at' => now(), 'posted_by' => $userId, 'created_by' => $userId]);
            $balance->update(['quantity_on_hand' => $afterQuantity, 'weighted_average_cost' => $afterCost, 'updated_by' => $userId]);
        }
        $reservation = $this->issue->handle($sequence, $userId);
        $transfer->update(['document_number_reservation_id' => $reservation->id,
            'transfer_number' => $reservation->document_number, 'status' => InventoryTransferStatus::Released,
            'released_at' => now(), 'released_by' => $userId, 'updated_by' => $userId]);
    }

    private function receive(InventoryTransfer $transfer, int $userId): void
    {
        foreach ($transfer->lines as $line) {
            if ($line->source_unit_cost === null || $line->total_cost === null) {
                throw ValidationException::withMessages(['lines' => 'Released transfer costing details are incomplete.']);
            }
            $balance = InventoryBalance::query()->where('product_service_id', $line->product_service_id)
                ->where('warehouse_id', $transfer->destination_warehouse_id)->lockForUpdate()->first();
            $balance ??= InventoryBalance::query()->create(['product_service_id' => $line->product_service_id,
                'warehouse_id' => $transfer->destination_warehouse_id, 'updated_by' => $userId]);
            $afterQuantity = bcadd($balance->quantity_on_hand, $line->quantity, 4);
            $afterCost = bcdiv(bcadd(bcmul($balance->quantity_on_hand, $balance->weighted_average_cost, 4),
                $line->total_cost, 4), $afterQuantity, 4);
            $line->movements()->create(['product_service_id' => $line->product_service_id,
                'warehouse_id' => $transfer->destination_warehouse_id, 'type' => InventoryMovementType::TransferIn,
                'movement_date' => now()->toDateString(), 'quantity' => $line->quantity,
                'unit_cost' => $line->source_unit_cost, 'total_cost' => $line->total_cost,
                'balance_quantity_before' => $balance->quantity_on_hand,
                'balance_average_cost_before' => $balance->weighted_average_cost,
                'balance_quantity_after' => $afterQuantity, 'balance_average_cost_after' => $afterCost,
                'status' => InventoryMovementStatus::Posted, 'posted_at' => now(), 'posted_by' => $userId, 'created_by' => $userId]);
            $balance->update(['quantity_on_hand' => $afterQuantity, 'weighted_average_cost' => $afterCost, 'updated_by' => $userId]);
        }
        $transfer->update(['status' => InventoryTransferStatus::Received, 'received_at' => now(),
            'received_by' => $userId, 'updated_by' => $userId]);
    }

    private function void(InventoryTransfer $transfer, int $userId, string $reason): void
    {
        if (in_array($transfer->status, [InventoryTransferStatus::Released, InventoryTransferStatus::InTransit,
            InventoryTransferStatus::Received, InventoryTransferStatus::Completed], true)) {
            foreach ($transfer->lines as $line) {
                $this->reverseLine($transfer, $line, $userId);
            }
        }
        $transfer->update(['status' => InventoryTransferStatus::Voided, 'voided_at' => now(),
            'voided_by' => $userId, 'void_reason' => $reason, 'updated_by' => $userId]);
    }

    private function reverseLine(InventoryTransfer $transfer, InventoryTransferLine $line, int $userId): void
    {
        if ($line->source_unit_cost === null || $line->total_cost === null) {
            throw ValidationException::withMessages(['lines' => 'Released transfer costing details are incomplete.']);
        }
        $inMovement = InventoryMovement::query()->whereBelongsTo($line, 'transferLine')
            ->where('type', InventoryMovementType::TransferIn)->whereNull('reversal_of_id')->lockForUpdate()->first();
        if ($inMovement) {
            $destination = InventoryBalance::query()->where('product_service_id', $line->product_service_id)
                ->where('warehouse_id', $transfer->destination_warehouse_id)->lockForUpdate()->firstOrFail();
            InventoryWorkflow::assertStockAvailable($destination->quantity_on_hand, $line->quantity);
            $afterQuantity = bcsub($destination->quantity_on_hand, $line->quantity, 4);
            $afterCost = bccomp($afterQuantity, '0', 4) === 0 ? '0.0000'
                : bcdiv(bcsub(bcmul($destination->quantity_on_hand, $destination->weighted_average_cost, 4),
                    $line->total_cost, 4), $afterQuantity, 4);
            $this->reversalMovement($line, $inMovement, $transfer->destination_warehouse_id,
                InventoryMovementType::TransferOut, bcmul($line->quantity, '-1', 4),
                bcmul($line->total_cost, '-1', 4), $destination, $afterQuantity, $afterCost, $userId);
            $destination->update(['quantity_on_hand' => $afterQuantity, 'weighted_average_cost' => $afterCost, 'updated_by' => $userId]);
        }
        $outMovement = InventoryMovement::query()->whereBelongsTo($line, 'transferLine')
            ->where('type', InventoryMovementType::TransferOut)->whereNull('reversal_of_id')->lockForUpdate()->firstOrFail();
        $source = InventoryBalance::query()->where('product_service_id', $line->product_service_id)
            ->where('warehouse_id', $transfer->source_warehouse_id)->lockForUpdate()->firstOrFail();
        $afterQuantity = bcadd($source->quantity_on_hand, $line->quantity, 4);
        $afterCost = bcdiv(bcadd(bcmul($source->quantity_on_hand, $source->weighted_average_cost, 4),
            $line->total_cost, 4), $afterQuantity, 4);
        $this->reversalMovement($line, $outMovement, $transfer->source_warehouse_id,
            InventoryMovementType::TransferIn, $line->quantity, $line->total_cost,
            $source, $afterQuantity, $afterCost, $userId);
        $source->update(['quantity_on_hand' => $afterQuantity, 'weighted_average_cost' => $afterCost, 'updated_by' => $userId]);
    }

    private function reversalMovement(InventoryTransferLine $line, InventoryMovement $original, int $warehouseId,
        InventoryMovementType $type, string $quantity, string $totalCost, InventoryBalance $balance,
        string $afterQuantity, string $afterCost, int $userId): void
    {
        InventoryMovement::query()->create(['inventory_transfer_line_id' => $line->id, 'reversal_of_id' => $original->id,
            'product_service_id' => $line->product_service_id, 'warehouse_id' => $warehouseId, 'type' => $type,
            'movement_date' => now()->toDateString(), 'quantity' => $quantity, 'unit_cost' => $line->source_unit_cost,
            'total_cost' => $totalCost, 'balance_quantity_before' => $balance->quantity_on_hand,
            'balance_average_cost_before' => $balance->weighted_average_cost, 'balance_quantity_after' => $afterQuantity,
            'balance_average_cost_after' => $afterCost, 'status' => InventoryMovementStatus::Posted,
            'posted_at' => now(), 'posted_by' => $userId, 'created_by' => $userId]);
    }
}
