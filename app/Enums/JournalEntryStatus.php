<?php

namespace App\Enums;

use App\Enums\Concerns\HasStatusTransitions;

enum JournalEntryStatus: string
{
    use HasStatusTransitions;

    case Draft = 'draft';
    case Posted = 'posted';
    case Reversed = 'reversed';
    case Voided = 'voided';

    /** @return list<self> */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Draft => [self::Posted, self::Voided],
            self::Posted => [self::Reversed, self::Voided],
            self::Reversed, self::Voided => [],
        };
    }
}
