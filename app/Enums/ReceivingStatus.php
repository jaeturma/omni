<?php

namespace App\Enums;

use App\Enums\Concerns\HasStatusTransitions;

enum ReceivingStatus: string
{
    use HasStatusTransitions;

    case Draft = 'draft';
    case Received = 'received';
    case Inspected = 'inspected';
    case Accepted = 'accepted';
    case PartiallyAccepted = 'partially_accepted';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';

    /** @return list<self> */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Draft => [self::Received, self::Cancelled],
            self::Received => [self::Inspected, self::Accepted, self::PartiallyAccepted, self::Rejected, self::Cancelled],
            self::Inspected => [self::Accepted, self::PartiallyAccepted, self::Rejected, self::Cancelled],
            self::Accepted, self::PartiallyAccepted, self::Rejected => [self::Cancelled],
            default => [],
        };
    }
}
