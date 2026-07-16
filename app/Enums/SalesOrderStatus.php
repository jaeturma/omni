<?php

namespace App\Enums;

use App\Enums\Concerns\HasStatusTransitions;

enum SalesOrderStatus: string
{
    use HasStatusTransitions;

    case Draft = 'draft';
    case Confirmed = 'confirmed';
    case PartiallyFulfilled = 'partially_fulfilled';
    case Fulfilled = 'fulfilled';
    case Closed = 'closed';
    case Cancelled = 'cancelled';

    /** @return list<self> */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Draft => [self::Confirmed, self::Cancelled],
            self::Confirmed => [self::PartiallyFulfilled, self::Fulfilled, self::Cancelled],
            self::PartiallyFulfilled => [self::Fulfilled, self::Cancelled],
            self::Fulfilled => [self::Closed],
            default => [],
        };
    }
}
