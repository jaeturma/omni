<?php

namespace App\Enums;

use App\Enums\Concerns\HasStatusTransitions;

enum InventoryMovementStatus: string
{
    use HasStatusTransitions;

    case Draft = 'draft';
    case Posted = 'posted';
    case Reversed = 'reversed';

    /** @return list<self> */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Draft => [self::Posted],
            self::Posted => [self::Reversed],
            self::Reversed => [],
        };
    }
}
