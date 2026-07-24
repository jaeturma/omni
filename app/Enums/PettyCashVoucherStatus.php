<?php

namespace App\Enums;

use App\Enums\Concerns\HasStatusTransitions;

enum PettyCashVoucherStatus: string
{
    use HasStatusTransitions;

    case Draft = 'draft';
    case Released = 'released';
    case Liquidated = 'liquidated';
    case Overdue = 'overdue';
    case Voided = 'voided';

    /** @return list<self> */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Draft => [self::Released, self::Voided],
            self::Released => [self::Liquidated, self::Overdue, self::Voided],
            self::Overdue => [self::Liquidated, self::Voided],
            self::Liquidated => [self::Voided],
            self::Voided => [],
        };
    }
}
