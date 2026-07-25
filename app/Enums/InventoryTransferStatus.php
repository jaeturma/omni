<?php

namespace App\Enums;

use App\Enums\Concerns\HasStatusTransitions;

enum InventoryTransferStatus: string
{
    use HasStatusTransitions;

    case Draft = 'draft';
    case Approved = 'approved';
    case Released = 'released';
    case InTransit = 'in_transit';
    case Received = 'received';
    case Completed = 'completed';
    case Voided = 'voided';

    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Draft => [self::Approved, self::Voided],
            self::Approved => [self::Released, self::Voided],
            self::Released => [self::InTransit, self::Voided],
            self::InTransit => [self::Received, self::Voided],
            self::Received => [self::Completed, self::Voided],
            self::Completed => [self::Voided],
            self::Voided => [],
        };
    }
}
