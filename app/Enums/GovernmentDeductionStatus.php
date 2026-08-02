<?php

namespace App\Enums;

use App\Enums\Concerns\HasStatusTransitions;

enum GovernmentDeductionStatus: string
{
    use HasStatusTransitions;

    case Pending = 'pending';
    case Received = 'received';
    case Verified = 'verified';
    case Rejected = 'rejected';
    case Applied = 'applied';
    case Voided = 'voided';

    /** @return list<self> */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Pending => [self::Received, self::Rejected, self::Voided],
            self::Received => [self::Verified, self::Rejected, self::Voided],
            self::Verified => [self::Applied, self::Rejected, self::Voided],
            self::Rejected => [self::Received, self::Voided],
            self::Applied => [self::Voided],
            self::Voided => [],
        };
    }
}
