<?php

namespace App\Support\Import;

use App\Enums\Grader;
use App\Enums\ItemStatus;
use App\Enums\RawCondition;
use App\Enums\Sport;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use Throwable;

/**
 * Lenient readers for the messy values people type into card spreadsheets.
 *
 * Each reader returns null for a blank cell and throws {@see InvalidImportRow}
 * when a non-blank value can't be understood and guessing would be wrong.
 */
final class ValueParser
{
    /**
     * @var list<string>
     */
    private const array TRUTHY = ['y', 'yes', 'x', '1', 'true', 't', 'rc', 'rookie', 'auto', 'au', 'relic', 'patch', 'mem', 'sp', 'ssp', '✓', '✔', '*', 'on'];

    /**
     * @var array<string, Sport>
     */
    private const array SPORT_ALIASES = [
        'mlb' => Sport::Baseball,
        'nba' => Sport::Basketball,
        'wnba' => Sport::Basketball,
        'nfl' => Sport::Football,
        'nhl' => Sport::Hockey,
        'mls' => Sport::Soccer,
        'futbol' => Sport::Soccer,
        'pga' => Sport::Golf,
        'nascar' => Sport::Racing,
        'f1' => Sport::Racing,
        'formula1' => Sport::Racing,
        'wwe' => Sport::Wrestling,
        'ufc' => Sport::Other,
        'multisport' => Sport::MultiSport,
        'multi' => Sport::MultiSport,
    ];

    /**
     * Condition shorthand (letters only, lower case) to the raw condition scale.
     *
     * @var array<string, RawCondition>
     */
    private const array CONDITIONS = [
        'gemmint' => RawCondition::GemMint,
        'gemmt' => RawCondition::GemMint,
        'gem' => RawCondition::GemMint,
        'gm' => RawCondition::GemMint,
        'mint' => RawCondition::Mint,
        'mt' => RawCondition::Mint,
        'nearmintmint' => RawCondition::NearMintMint,
        'nmmt' => RawCondition::NearMintMint,
        'nmmint' => RawCondition::NearMintMint,
        'nearmint' => RawCondition::NearMint,
        'nm' => RawCondition::NearMint,
        'excellentmint' => RawCondition::ExcellentMint,
        'exmt' => RawCondition::ExcellentMint,
        'excellent' => RawCondition::Excellent,
        'ex' => RawCondition::Excellent,
        'verygoodexcellent' => RawCondition::VeryGoodExcellent,
        'vgex' => RawCondition::VeryGoodExcellent,
        'verygood' => RawCondition::VeryGood,
        'vg' => RawCondition::VeryGood,
        'good' => RawCondition::Good,
        'gd' => RawCondition::Good,
        'g' => RawCondition::Good,
        'fair' => RawCondition::Fair,
        'fr' => RawCondition::Fair,
        'poor' => RawCondition::Poor,
        'pr' => RawCondition::Poor,
    ];

    /**
     * Date formats tried in order. US month-first formats come before day-first ones.
     *
     * @var list<string>
     */
    private const array DATE_FORMATS = ['Y-m-d', 'n/j/Y', 'n/j/y', 'n-j-Y', 'n-j-y', 'Y/n/j', 'n.j.Y', 'M j, Y', 'M j Y', 'F j, Y', 'F j Y', 'j M Y', 'j F Y', 'M Y', 'F Y', 'Y-m-d H:i:s', 'Y-m-d\TH:i:sP'];

    public static function blank(?string $value): bool
    {
        return $value === null || trim($value) === '' || in_array(trim($value), ['-', '—', 'n/a', 'N/A', 'NA'], true);
    }

    public static function text(?string $value): ?string
    {
        return self::blank($value) ? null : Str::squish((string) $value);
    }

    public static function boolean(?string $value): ?bool
    {
        if (self::blank($value)) {
            return null;
        }

        return in_array(Str::lower(trim((string) $value)), self::TRUTHY, true);
    }

    /**
     * "$1,200.50", "1200", "USD 15" → dollars as a numeric string.
     */
    public static function money(?string $value, string $label): ?string
    {
        if (self::blank($value)) {
            return null;
        }

        $cleaned = preg_replace('/[^0-9.\-]/', '', str_replace(',', '', (string) $value)) ?? '';

        if (! is_numeric($cleaned) || (float) $cleaned < 0) {
            throw new InvalidImportRow("Couldn’t read the {$label} “".trim((string) $value).'” as an amount.');
        }

        return number_format((float) $cleaned, 2, '.', '');
    }

    /**
     * "1989", "'89", "2023-24", "2023/2024" → [year, season].
     *
     * @return array{0: int, 1: string|null}|null
     */
    public static function year(?string $value): ?array
    {
        if (self::blank($value)) {
            return null;
        }

        $value = trim((string) $value);

        if (preg_match('/^((?:18|19|20)\d{2})\s*[-\/–]\s*(\d{2}|\d{4})$/u', $value, $match)) {
            return [(int) $match[1], $match[1].'-'.substr($match[2], -2)];
        }

        if (preg_match('/^(?:18|19|20)\d{2}$/', $value)) {
            return [(int) $value, null];
        }

        if (preg_match('/^[\'’]?(\d{2})$/u', $value, $match)) {
            $short = (int) $match[1];

            return [$short > (int) now()->format('y') + 1 ? 1900 + $short : 2000 + $short, null];
        }

        if (preg_match('/\b((?:18|19|20)\d{2})\b/', $value, $match)) {
            return [(int) $match[1], null];
        }

        throw new InvalidImportRow("Couldn’t read the year “{$value}”.");
    }

    public static function sport(?string $value): ?Sport
    {
        if (self::blank($value)) {
            return null;
        }

        $key = preg_replace('/[^a-z0-9]/', '', Str::lower((string) $value)) ?? '';

        foreach (Sport::cases() as $sport) {
            if ($key === str_replace('-', '', $sport->value)) {
                return $sport;
            }
        }

        return self::SPORT_ALIASES[$key] ?? throw new InvalidImportRow('Unknown sport “'.trim((string) $value).'”.');
    }

    /**
     * "PSA", "psa/dna", "Beckett" → Grader. Unrecognised companies become "Other".
     */
    public static function grader(?string $value): ?Grader
    {
        if (self::blank($value)) {
            return null;
        }

        $upper = Str::upper(trim((string) $value));

        return match (true) {
            str_starts_with($upper, 'PSA') => Grader::Psa,
            str_starts_with($upper, 'BGS'), str_starts_with($upper, 'BECKETT') => Grader::Bgs,
            str_starts_with($upper, 'BVG') => Grader::Bvg,
            str_starts_with($upper, 'SGC') => Grader::Sgc,
            str_starts_with($upper, 'CGC') => Grader::Cgc,
            str_starts_with($upper, 'CSG') => Grader::Csg,
            str_starts_with($upper, 'HGA') => Grader::Hga,
            default => Grader::Other,
        };
    }

    /**
     * Split a grade cell. "PSA 10" → [PSA, "10"]; "9.5" → [null, "9.5"]; "Auth" → [null, "Authentic"].
     *
     * @return array{0: Grader|null, 1: string}|null
     */
    public static function grade(?string $value): ?array
    {
        if (self::blank($value)) {
            return null;
        }

        $value = Str::squish((string) $value);

        if (preg_match('/^([A-Za-z\/]+)\s*[- ]?\s*(\d{1,2}(?:\.\d)?|auth(?:entic)?)(?:\s|$)/i', $value, $match)) {
            return [self::grader($match[1]), self::normalizeGrade($match[2])];
        }

        return [null, self::normalizeGrade($value)];
    }

    public static function condition(?string $value): ?RawCondition
    {
        if (self::blank($value)) {
            return null;
        }

        $key = preg_replace('/[^a-z]/', '', Str::lower((string) $value)) ?? '';

        return RawCondition::tryFrom(Str::lower(trim((string) $value))) ?? self::CONDITIONS[$key] ?? null;
    }

    public static function status(?string $value): ?ItemStatus
    {
        if (self::blank($value)) {
            return null;
        }

        $key = Str::slug((string) $value);

        foreach (ItemStatus::cases() as $status) {
            if ($key === $status->value || $key === Str::slug($status->label())) {
                return $status;
            }
        }

        return match ($key) {
            'in-collection', 'have', 'in-hand', 'pc' => ItemStatus::Owned,
            'for-sale', 'on-ebay' => ItemStatus::Listed,
            'grading', 'submitted' => ItemStatus::AtGrading,
            'gifted', 'lost', 'given-away' => ItemStatus::Gone,
            default => throw new InvalidImportRow('Unknown status “'.trim((string) $value).'”.'),
        };
    }

    /**
     * "12" → [12, null]; "12/99" → [12, 99].
     *
     * @return array{0: int, 1: int|null}|null
     */
    public static function serial(?string $value): ?array
    {
        if (self::blank($value)) {
            return null;
        }

        $value = trim((string) $value);

        if (preg_match('/^#?\s*(\d+)\s*(?:\/|of)\s*(\d+)$/i', $value, $match)) {
            return [(int) $match[1], (int) $match[2]];
        }

        if (preg_match('/^#?\s*(\d+)$/', $value, $match)) {
            return [(int) $match[1], null];
        }

        throw new InvalidImportRow("Couldn’t read the serial number “{$value}”.");
    }

    /**
     * "99", "/99", "#/99" → 99.
     */
    public static function printRun(?string $value): ?int
    {
        if (self::blank($value)) {
            return null;
        }

        if (preg_match('/^#?\s*\/?\s*(\d+)$/', trim((string) $value), $match) && (int) $match[1] > 0) {
            return (int) $match[1];
        }

        throw new InvalidImportRow('Couldn’t read the print run “'.trim((string) $value).'”.');
    }

    public static function quantity(?string $value): int
    {
        if (self::blank($value)) {
            return 1;
        }

        $quantity = filter_var(trim((string) $value), FILTER_VALIDATE_INT);

        if ($quantity === false || $quantity < 1 || $quantity > 100) {
            throw new InvalidImportRow('Quantity must be a whole number from 1 to 100, got “'.trim((string) $value).'”.');
        }

        return $quantity;
    }

    /**
     * Returns the date as Y-m-d, trying common spreadsheet formats.
     */
    public static function date(?string $value): ?string
    {
        if (self::blank($value)) {
            return null;
        }

        $value = Str::squish((string) $value);

        if (is_numeric($value) && (int) $value > 20000 && (int) $value < 80000) {
            return CarbonImmutable::create(1899, 12, 30)->addDays((int) $value)->toDateString();
        }

        foreach (self::DATE_FORMATS as $format) {
            try {
                $date = CarbonImmutable::createFromFormat('!'.$format, $value);
            } catch (Throwable) {
                continue;
            }

            if ($date !== null && $date->year >= 1900 && $date->year <= 2100) {
                return $date->toDateString();
            }
        }

        throw new InvalidImportRow("Couldn’t read the date “{$value}”.");
    }

    /**
     * Split a list cell on commas, semicolons or pipes.
     *
     * @return array<int, string>
     */
    public static function list(?string $value, string $pattern = '/[,;|]/'): array
    {
        if (self::blank($value)) {
            return [];
        }

        return collect(preg_split($pattern, (string) $value) ?: [])
            ->map(fn (string $part): string => Str::squish($part))
            ->filter()
            ->values()
            ->all();
    }

    private static function normalizeGrade(string $grade): string
    {
        return match (true) {
            (bool) preg_match('/^auth(entic)?$/i', $grade) => 'Authentic',
            default => $grade,
        };
    }
}
