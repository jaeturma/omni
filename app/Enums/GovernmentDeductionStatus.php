<?php

namespace App\Enums;

use App\Enums\Concerns\HasStatusTransitions;

enum GovernmentDeductionStatus: string
{
    use HasStatusTransitions;

    case Pending = 'pending';
    case Received = 'received';
    case Verified = 'verified';
    case Voided = 'voided';

    /** @return list<self> */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Pending => [self::Received, self::Voided],
            self::Received => [self::Verified, self::Voided],
            self::Verified => [self::Voided],
            self::Voided => [],
        };
    }
}
