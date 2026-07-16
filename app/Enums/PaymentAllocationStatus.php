<?php

namespace App\Enums;

use App\Enums\Concerns\HasStatusTransitions;

enum PaymentAllocationStatus: string
{
    use HasStatusTransitions;

    case Active = 'active';
    case Reversed = 'reversed';

    /** @return list<self> */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Active => [self::Reversed],
            self::Reversed => [],
        };
    }
}
