<?php

namespace App\Enums;

use App\Enums\Concerns\HasStatusTransitions;

enum CashDisbursementStatus: string
{
    use HasStatusTransitions;

    case Draft = 'draft';
    case Posted = 'posted';
    case Released = 'released';
    case Cleared = 'cleared';
    case Stopped = 'stopped';
    case Voided = 'voided';

    /** @return list<self> */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Draft => [self::Posted],
            self::Posted => [self::Released, self::Cleared, self::Stopped, self::Voided],
            self::Released => [self::Cleared, self::Stopped, self::Voided],
            self::Cleared => [self::Stopped, self::Voided],
            self::Stopped, self::Voided => [],
        };
    }
}
