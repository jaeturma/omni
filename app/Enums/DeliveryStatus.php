<?php

namespace App\Enums;

use App\Enums\Concerns\HasStatusTransitions;

enum DeliveryStatus: string
{
    use HasStatusTransitions;

    case Draft = 'draft';
    case Released = 'released';
    case Delivered = 'delivered';
    case Cancelled = 'cancelled';

    /** @return list<self> */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Draft => [self::Released, self::Cancelled],
            self::Released => [self::Delivered, self::Cancelled],
            default => [],
        };
    }
}
