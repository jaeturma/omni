<?php

namespace App\Enums;

use App\Enums\Concerns\HasStatusTransitions;

enum InventoryAdjustmentStatus: string
{
    use HasStatusTransitions;

    case Draft = 'draft';
    case Approved = 'approved';
    case Posted = 'posted';
    case Voided = 'voided';

    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Draft => [self::Approved],
            self::Approved => [self::Posted],
            self::Posted => [self::Voided],
            self::Voided => [],
        };
    }
}
