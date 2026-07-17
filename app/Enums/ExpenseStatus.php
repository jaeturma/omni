<?php

namespace App\Enums;

use App\Enums\Concerns\HasStatusTransitions;

enum ExpenseStatus: string
{
    use HasStatusTransitions;

    case Draft = 'draft';
    case Approved = 'approved';
    case Posted = 'posted';
    case Paid = 'paid';
    case Voided = 'voided';

    /** @return list<self> */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Draft => [self::Approved, self::Voided],
            self::Approved => [self::Posted, self::Voided],
            self::Posted => [self::Paid, self::Voided],
            self::Paid => [self::Voided],
            default => [],
        };
    }
}
