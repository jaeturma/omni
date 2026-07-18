<?php

namespace App\Enums;

use App\Enums\Concerns\HasStatusTransitions;

enum ExpenseStatus: string
{
    use HasStatusTransitions;

    case Draft = 'draft';
    case Approved = 'approved';
    case Paid = 'paid';
    case Reimbursable = 'reimbursable';
    case Voided = 'voided';

    /** @return list<self> */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Draft => [self::Approved, self::Paid, self::Reimbursable, self::Voided],
            self::Approved => [self::Paid, self::Reimbursable, self::Voided],
            self::Reimbursable => [self::Paid, self::Voided],
            self::Paid => [self::Voided],
            default => [],
        };
    }
}
