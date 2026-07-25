<?php

namespace App\Models;

use App\Enums\InventoryTransferStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * @property InventoryTransferStatus $status
 * @property Carbon $transfer_date
 */
#[Fillable(['document_number_reservation_id', 'transfer_number', 'transfer_date', 'fiscal_period_id', 'source_warehouse_id', 'destination_warehouse_id', 'reference', 'notes', 'status', 'approved_at', 'approved_by', 'released_at', 'released_by', 'in_transit_at', 'in_transit_by', 'received_at', 'received_by', 'completed_at', 'completed_by', 'voided_at', 'voided_by', 'void_reason', 'created_by', 'updated_by'])]
class InventoryTransfer extends Model
{
    protected $attributes = ['status' => 'draft'];

    protected static function booted(): void
    {
        static::updating(function (self $transfer): void {
            if (in_array($transfer->getRawOriginal('status'), ['released', 'in_transit', 'received', 'completed'], true)
                && array_diff(array_keys($transfer->getDirty()), ['status', 'in_transit_at', 'in_transit_by', 'received_at', 'received_by', 'completed_at', 'completed_by', 'voided_at', 'voided_by', 'void_reason', 'updated_by', 'updated_at'])) {
                throw new LogicException('Released inventory transfers are immutable.');
            }
        });
    }

    /** @return HasMany<InventoryTransferLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(InventoryTransferLine::class)->orderBy('line_number');
    }

    public function fiscalPeriod(): BelongsTo
    {
        return $this->belongsTo(FiscalPeriod::class);
    }

    public function sourceWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'source_warehouse_id');
    }

    public function destinationWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'destination_warehouse_id');
    }

    protected function casts(): array
    {
        return ['transfer_date' => 'date', 'status' => InventoryTransferStatus::class, 'approved_at' => 'datetime',
            'released_at' => 'datetime', 'in_transit_at' => 'datetime', 'received_at' => 'datetime',
            'completed_at' => 'datetime', 'voided_at' => 'datetime'];
    }
}
