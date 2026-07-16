<?php

namespace App\Enums;

use App\Enums\Concerns\HasStatusTransitions;

enum CustomerPaymentStatus: string
{
    use HasStatusTransitions;

    case Draft = 'draft';
    case Posted = 'posted';
    case PartiallyAllocated = 'partially_allocated';
    case FullyAllocated = 'fully_allocated';
    case Voided = 'voided';

    /** @return list<self> */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Draft => [self::Posted],
            self::Posted => [self::PartiallyAllocated, self::FullyAllocated, self::Voided],
            self::PartiallyAllocated => [self::FullyAllocated, self::Voided],
            default => [],
        };
    }
}
