<?php

namespace App\Support;

use Illuminate\Support\Str;

class SensitiveData
{
    public static function mask(?string $value, int $visible = 4, string $fallback = 'Not provided'): string
    {
        if (blank($value)) {
            return $fallback;
        }

        $compact = preg_replace('/\s+/', '', $value) ?? $value;
        $visible = max(0, min($visible, Str::length($compact)));

        return str_repeat('*', Str::length($compact) - $visible).Str::substr($compact, -$visible);
    }
}
