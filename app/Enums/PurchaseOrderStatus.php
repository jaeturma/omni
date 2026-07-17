<?php

namespace App\Enums;

use App\Enums\Concerns\HasStatusTransitions;

enum PurchaseOrderStatus: string
{
    use HasStatusTransitions;

    case Draft = 'draft';
    case Approved = 'approved';
    case Issued = 'issued';
    case PartiallyReceived = 'partially_received';
    case FullyReceived = 'fully_received';
    case Closed = 'closed';
    case Cancelled = 'cancelled';

    /** @return list<self> */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Draft => [self::Approved, self::Cancelled],
            self::Approved => [self::Issued, self::Cancelled],
            self::Issued => [self::PartiallyReceived, self::FullyReceived, self::Cancelled],
            self::PartiallyReceived => [self::FullyReceived, self::Cancelled],
            self::FullyReceived => [self::Closed],
            default => [],
        };
    }
}
