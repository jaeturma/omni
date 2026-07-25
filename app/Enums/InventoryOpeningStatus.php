<?php

namespace App\Enums;

use App\Enums\Concerns\HasStatusTransitions;

enum InventoryOpeningStatus: string
{
    use HasStatusTransitions;

    case Draft = 'draft';
    case Posted = 'posted';
    case Voided = 'voided';

    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Draft => [self::Posted],
            self::Posted => [self::Voided],
            self::Voided => [],
        };
    }
}
