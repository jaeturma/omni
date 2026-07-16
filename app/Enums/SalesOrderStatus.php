<?php

namespace App\Enums;

use App\Enums\Concerns\HasStatusTransitions;

enum SalesOrderStatus: string
{
    use HasStatusTransitions;

    case Draft = 'draft';
    case Confirmed = 'confirmed';
    case PartiallyDelivered = 'partially_delivered';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    /** @return list<self> */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Draft => [self::Confirmed, self::Cancelled],
            self::Confirmed => [self::PartiallyDelivered, self::Completed, self::Cancelled],
            self::PartiallyDelivered => [self::Completed, self::Cancelled],
            default => [],
        };
    }
}
