<?php

namespace App\Enums;

use App\Enums\Concerns\HasStatusTransitions;

enum PhysicalCountStatus: string
{
    use HasStatusTransitions;

    case Draft = 'draft';
    case Counting = 'counting';
    case Submitted = 'submitted';
    case Approved = 'approved';
    case Posted = 'posted';
    case Voided = 'voided';

    /** @return list<self> */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Draft => [self::Counting, self::Voided],
            self::Counting => [self::Submitted, self::Voided],
            self::Submitted => [self::Counting, self::Approved, self::Voided],
            self::Approved => [self::Counting, self::Posted, self::Voided],
            self::Posted => [self::Voided],
            self::Voided => [],
        };
    }
}
