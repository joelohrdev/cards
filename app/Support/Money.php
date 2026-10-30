<?php

namespace App\Support;

/**
 * Converts between user-entered dollar amounts and stored integer cents.
 */
final class Money
{
    public static function toCents(mixed $dollars): ?int
    {
        if ($dollars === null || $dollars === '') {
            return null;
        }

        $normalized = str_replace([',', '$', ' '], '', (string) $dollars);

        if (! is_numeric($normalized)) {
            return null;
        }

        return (int) round(((float) $normalized) * 100);
    }

    public static function toDollars(?int $cents): ?string
    {
        return $cents === null ? null : number_format($cents / 100, 2, '.', '');
    }
}
