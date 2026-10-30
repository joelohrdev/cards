<?php

namespace App\Support\Import;

use Illuminate\Support\LazyCollection;
use SplFileObject;

/**
 * Streams a spreadsheet export (CSV, semicolon or tab separated) one row at a time,
 * so a file with tens of thousands of rows never has to fit in memory.
 */
final class CsvReader
{
    private string $delimiter;

    public function __construct(private string $path)
    {
        $this->delimiter = $this->detectDelimiter();
    }

    /**
     * The header row, trimmed and with any byte-order mark removed.
     *
     * @return list<string>
     */
    public function headers(): array
    {
        $first = $this->lines()->first();

        return $first === null ? [] : array_map(fn (string $header): string => trim($header), $first);
    }

    /**
     * Data rows (everything after the header), keyed by their zero-based position.
     *
     * @return LazyCollection<int, list<string>>
     */
    public function rows(): LazyCollection
    {
        return $this->lines()->skip(1)->values();
    }

    /**
     * @return array<int, list<string>>
     */
    public function preview(int $limit = 5): array
    {
        return $this->rows()->take($limit)->values()->all();
    }

    public function countRows(): int
    {
        return $this->rows()->count();
    }

    /**
     * Every non-blank line of the file, parsed into cells.
     *
     * @return LazyCollection<int, list<string>>
     */
    private function lines(): LazyCollection
    {
        return LazyCollection::make(function () {
            $file = new SplFileObject($this->path, 'r');
            $file->setFlags(SplFileObject::READ_CSV | SplFileObject::READ_AHEAD | SplFileObject::SKIP_EMPTY | SplFileObject::DROP_NEW_LINE);
            $file->setCsvControl($this->delimiter, '"', '');
            $isFirst = true;

            foreach ($file as $cells) {
                if (! is_array($cells) || $cells === [null]) {
                    continue;
                }

                $cells = array_map(fn ($cell): string => $this->toUtf8((string) $cell), $cells);

                if ($isFirst) {
                    $cells[0] = preg_replace('/^\xEF\xBB\xBF/', '', $cells[0]) ?? $cells[0];
                    $isFirst = false;
                }

                if (implode('', array_map('trim', $cells)) === '') {
                    continue;
                }

                yield array_values($cells);
            }
        });
    }

    /**
     * Pick whichever of comma, semicolon or tab splits the first line into the most cells.
     */
    private function detectDelimiter(): string
    {
        $file = new SplFileObject($this->path, 'r');
        $firstLine = (string) $file->fgets();

        $counts = collect([',', ';', "\t"])
            ->mapWithKeys(fn (string $delimiter): array => [$delimiter => count(str_getcsv($firstLine, $delimiter, '"', ''))]);

        $best = $counts->sortDesc()->keys()->first();

        return $counts[$best] > 1 ? $best : ',';
    }

    /**
     * Spreadsheets exported on Windows are often Windows-1252 rather than UTF-8.
     */
    private function toUtf8(string $value): string
    {
        if ($value === '' || mb_check_encoding($value, 'UTF-8')) {
            return $value;
        }

        return mb_convert_encoding($value, 'UTF-8', 'Windows-1252');
    }
}
