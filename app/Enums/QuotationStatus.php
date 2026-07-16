<?php

namespace App\Enums;

use App\Enums\Concerns\HasStatusTransitions;

enum QuotationStatus: string
{
    use HasStatusTransitions;

    case Draft = 'draft';
    case Sent = 'sent';
    case Accepted = 'accepted';
    case Rejected = 'rejected';
    case Expired = 'expired';
    case Cancelled = 'cancelled';

    /** @return list<self> */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Draft => [self::Sent, self::Cancelled],
            self::Sent => [self::Accepted, self::Rejected, self::Expired, self::Cancelled],
            default => [],
        };
    }
}
