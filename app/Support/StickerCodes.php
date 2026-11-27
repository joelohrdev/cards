<?php

namespace App\Support;

use App\Models\CollectionItem;
use Illuminate\Support\Str;

/**
 * Sticker IDs: an optional code on a sticker on a physical card (or its sleeve), unique across
 * the collection, so a card in hand can be looked up in a second. Stored in capitals so "a12"
 * and "A12" are the same sticker.
 */
class StickerCodes
{
    public static function normalize(?string $code): ?string
    {
        $code = Str::upper(Str::squish((string) $code));

        return $code === '' ? null : $code;
    }

    /**
     * The code after the one used most recently: "A0041" → "A0042", skipping codes in use.
     * Null until a code ending in a number has been used.
     */
    public static function next(): ?string
    {
        $latest = CollectionItem::query()
            ->whereNotNull('sticker_code')
            ->latest('id')
            ->limit(50)
            ->pluck('sticker_code')
            ->first(fn (string $code): bool => preg_match('/\d+$/', $code) === 1);

        return $latest === null ? null : self::sequence($latest, 1, skipFirst: true)[0];
    }

    /**
     * $count unused codes starting at $code ("A0041" for 3 → A0041, A0042, A0043). A code that
     * doesn't end in a number can only be used once, so it's returned alone.
     *
     * @return list<string>
     */
    public static function sequence(string $code, int $count, bool $skipFirst = false): array
    {
        if (preg_match('/^(.*?)(\d+)$/', $code, $parts) !== 1) {
            return [$code];
        }

        [, $prefix, $digits] = $parts;
        $width = strlen($digits);
        $number = (int) $digits + ($skipFirst ? 1 : 0);
        $codes = [];

        while (count($codes) < $count) {
            $candidate = $prefix.str_pad((string) $number, $width, '0', STR_PAD_LEFT);

            if (! CollectionItem::query()->where('sticker_code', $candidate)->exists()) {
                $codes[] = $candidate;
            }

            $number++;
        }

        return $codes;
    }
}
