<?php

namespace App\Enums;

use App\Enums\Concerns\HasStatusTransitions;

enum QuotationStatus: string
{
    use HasStatusTransitions;

    case Draft = 'draft';
    case Submitted = 'submitted';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Expired = 'expired';
    case Converted = 'converted';
    case Cancelled = 'cancelled';

    /** @return list<self> */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Draft => [self::Submitted, self::Cancelled],
            self::Submitted => [self::Approved, self::Rejected, self::Expired, self::Cancelled],
            self::Approved => [self::Converted, self::Cancelled],
            default => [],
        };
    }
}
