<?php

namespace App\Support;

use Illuminate\Support\Str;

class SensitiveData
{
    public static function email(?string $value, string $fallback = 'Not provided'): string
    {
        if (blank($value) || ! str_contains($value, '@')) {
            return $fallback;
        }
        [$local, $domain] = explode('@', $value, 2);

        return Str::substr($local, 0, 1).str_repeat('*', max(1, Str::length($local) - 1)).'@'.$domain;
    }

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
