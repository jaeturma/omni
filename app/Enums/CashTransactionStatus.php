<?php

namespace App\Enums;

use App\Enums\Concerns\HasStatusTransitions;

enum CashTransactionStatus: string
{
    use HasStatusTransitions;

    case Draft = 'draft';
    case Posted = 'posted';
    case Voided = 'voided';

    /** @return list<self> */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Draft => [self::Posted],
            self::Posted => [self::Voided],
            self::Voided => [],
        };
    }
}
