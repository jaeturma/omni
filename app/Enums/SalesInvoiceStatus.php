<?php

namespace App\Enums;

use App\Enums\Concerns\HasStatusTransitions;

enum SalesInvoiceStatus: string
{
    use HasStatusTransitions;

    case Draft = 'draft';
    case Posted = 'posted';
    case PartiallyPaid = 'partially_paid';
    case Paid = 'paid';
    case Voided = 'voided';

    /** @return list<self> */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Draft => [self::Posted],
            self::Posted => [self::PartiallyPaid, self::Paid, self::Voided],
            self::PartiallyPaid => [self::Paid, self::Voided],
            default => [],
        };
    }
}
