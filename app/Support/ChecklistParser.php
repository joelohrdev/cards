<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Parses pasted checklist text into card attributes.
 *
 * Accepts one card per line in either form:
 *   "12 Mike Trout - Los Angeles Angels RC"
 *   "12<TAB>Mike Trout<TAB>Los Angeles Angels<TAB>RC"
 *
 * Trailing flags (RC, AU, AUTO, SP, SSP, RELIC, PATCH, /99) are recognised.
 */
class ChecklistParser
{
    /**
     * @return list<array{number: string, name: string, team: string|null, is_rookie: bool, is_autograph: bool, is_memorabilia: bool, is_short_print: bool, print_run: int|null}>
     */
    public function parse(string $text): array
    {
        $cards = [];

        foreach (preg_split('/\r\n|\r|\n/', $text) ?: [] as $line) {
            $line = trim($line);

            if ($line === '') {
                continue;
            }

            $card = str_contains($line, "\t") ? $this->parseColumns($line) : $this->parseLine($line);

            if ($card !== null) {
                $cards[] = $card;
            }
        }

        return $cards;
    }

    /**
     * @return array{number: string, name: string, team: string|null, is_rookie: bool, is_autograph: bool, is_memorabilia: bool, is_short_print: bool, print_run: int|null}|null
     */
    private function parseColumns(string $line): ?array
    {
        $columns = array_map('trim', explode("\t", $line));
        $number = ltrim($columns[0], '#');

        if ($number === '' || blank($columns[1] ?? null)) {
            return null;
        }

        return $this->withFlags($number, $columns[1], $columns[2] ?? null, implode(' ', array_slice($columns, 3)));
    }

    /**
     * @return array{number: string, name: string, team: string|null, is_rookie: bool, is_autograph: bool, is_memorabilia: bool, is_short_print: bool, print_run: int|null}|null
     */
    private function parseLine(string $line): ?array
    {
        if (! preg_match('/^#?(\S+)\s+(.+)$/', $line, $match)) {
            return null;
        }

        [$name, $team] = array_pad(preg_split('/\s+[-–—|]\s+/', $match[2], 2) ?: [], 2, null);

        $flags = '';
        $source = $team ?? $name;

        while (preg_match('/\s+(RC|AU|AUTO|SP|SSP|RELIC|PATCH|MEM|\/\d+)$/i', (string) $source, $flagMatch)) {
            $flags = $flagMatch[1].' '.$flags;
            $source = substr((string) $source, 0, -strlen($flagMatch[0]));
        }

        if ($team !== null) {
            $team = $source;
        } else {
            $name = $source;
        }

        return $this->withFlags($match[1], (string) $name, $team, $flags);
    }

    /**
     * @return array{number: string, name: string, team: string|null, is_rookie: bool, is_autograph: bool, is_memorabilia: bool, is_short_print: bool, print_run: int|null}
     */
    private function withFlags(string $number, string $name, ?string $team, string $flags): array
    {
        $tokens = collect(preg_split('/[\s,]+/', Str::upper($flags)) ?: [])->filter();
        $printRun = $tokens->first(fn (string $token): bool => (bool) preg_match('/^\/\d+$/', $token));

        return [
            'number' => $number,
            'name' => Str::squish($name),
            'team' => filled($team) ? Str::squish((string) $team) : null,
            'is_rookie' => $tokens->contains('RC'),
            'is_autograph' => $tokens->intersect(['AU', 'AUTO'])->isNotEmpty(),
            'is_memorabilia' => $tokens->intersect(['RELIC', 'PATCH', 'MEM'])->isNotEmpty(),
            'is_short_print' => $tokens->intersect(['SP', 'SSP'])->isNotEmpty(),
            'print_run' => $printRun !== null ? (int) ltrim($printRun, '/') : null,
        ];
    }
}
