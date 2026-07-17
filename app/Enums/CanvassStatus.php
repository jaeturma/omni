<?php

namespace App\Enums;

use App\Enums\Concerns\HasStatusTransitions;

enum CanvassStatus: string
{
    use HasStatusTransitions;

    case Draft = 'draft';
    case Open = 'open';
    case Evaluated = 'evaluated';
    case Awarded = 'awarded';
    case Cancelled = 'cancelled';

    /** @return list<self> */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Draft => [self::Open, self::Cancelled],
            self::Open => [self::Evaluated, self::Cancelled],
            self::Evaluated => [self::Awarded, self::Cancelled],
            default => [],
        };
    }
}
