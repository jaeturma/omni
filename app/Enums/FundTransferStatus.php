<?php

namespace App\Enums;

use App\Enums\Concerns\HasStatusTransitions;

enum FundTransferStatus: string
{
    use HasStatusTransitions;

    case Draft = 'draft';
    case Posted = 'posted';
    case InTransit = 'in_transit';
    case Completed = 'completed';
    case Failed = 'failed';
    case Voided = 'voided';

    /** @return list<self> */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Draft => [self::Posted],
            self::Posted => [self::InTransit, self::Completed, self::Failed, self::Voided],
            self::InTransit => [self::Completed, self::Failed, self::Voided],
            self::Completed => [self::Voided],
            self::Failed, self::Voided => [],
        };
    }
}
