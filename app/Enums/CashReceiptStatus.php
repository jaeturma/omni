<?php

namespace App\Enums;

use App\Enums\Concerns\HasStatusTransitions;

enum CashReceiptStatus: string
{
    use HasStatusTransitions;

    case Draft = 'draft';
    case Posted = 'posted';
    case Cleared = 'cleared';
    case Bounced = 'bounced';
    case Voided = 'voided';

    /** @return list<self> */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Draft => [self::Posted],
            self::Posted => [self::Cleared, self::Bounced, self::Voided],
            self::Cleared => [self::Bounced, self::Voided],
            self::Bounced, self::Voided => [],
        };
    }
}
