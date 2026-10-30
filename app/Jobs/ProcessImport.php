<?php

namespace App\Jobs;

use App\Actions\Catalog\ResolveCardSet;
use App\Actions\Collection\SaveCollectionItem;
use App\Enums\ImportStatus;
use App\Models\Import;
use App\Support\Import\CsvReader;
use App\Support\Import\ImportProgressConflict;
use App\Support\Import\ImportRowMapper;
use App\Support\Import\InvalidImportRow;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Imports a mapped CSV into the owner's collection.
 *
 * Each run handles at most {@see self::ROWS_PER_RUN} rows, committing every
 * {@see self::ROWS_PER_COMMIT} rows together with the import's `processed_rows`
 * offset, then queues itself again for the next slice. A retried or duplicated job
 * resumes from the committed offset, and a commit only lands if the offset is still
 * where this run found it, so no row is ever imported twice.
 */
class ProcessImport implements ShouldQueue
{
    use Queueable;

    public const int ROWS_PER_RUN = 500;

    public const int ROWS_PER_COMMIT = 50;

    /**
     * Kept below the database queue's 90 second `retry_after`.
     */
    public int $timeout = 80;

    public int $tries = 3;

    /**
     * @var list<int>
     */
    public array $backoff = [10, 60];

    public function __construct(public Import $import) {}

    public function handle(SaveCollectionItem $saveCollectionItem, ResolveCardSet $resolveCardSet): void
    {
        $import = $this->import->fresh(['user', 'tag']);

        if ($import === null || $import->status !== ImportStatus::Processing) {
            return;
        }

        $mapper = new ImportRowMapper($import, $resolveCardSet);
        $offset = $import->processed_rows;
        $rowsThisRun = 0;

        $rows = (new CsvReader(Storage::disk(Import::DISK)->path($import->path)))
            ->rows()
            ->skip($offset)
            ->take(self::ROWS_PER_RUN);

        try {
            foreach ($rows->chunk(self::ROWS_PER_COMMIT) as $chunk) {
                $chunk = $chunk->values()->all();
                $this->commitChunk($import, $mapper, $saveCollectionItem, $chunk, $offset);
                $offset += count($chunk);
                $rowsThisRun += count($chunk);
            }
        } catch (ImportProgressConflict) {
            return;
        }

        if ($rowsThisRun < self::ROWS_PER_RUN) {
            $import->update(['status' => ImportStatus::Completed, 'completed_at' => now()]);

            return;
        }

        self::dispatch($import);
    }

    /**
     * Mark the import as failed once every attempt is used up, so the status page stops polling.
     */
    public function failed(?Throwable $exception): void
    {
        $this->import->update(['status' => ImportStatus::Failed, 'completed_at' => now()]);
    }

    /**
     * Import a slice of rows and advance the offset in one transaction.
     *
     * @param  array<int, list<string>>  $chunk
     *
     * @throws ImportProgressConflict when another run already committed these rows.
     */
    private function commitChunk(Import $import, ImportRowMapper $mapper, SaveCollectionItem $saveCollectionItem, array $chunk, int $offset): void
    {
        DB::transaction(function () use ($import, $mapper, $saveCollectionItem, $chunk, $offset): void {
            $imported = 0;
            $failures = $import->failures ?? [];

            foreach ($chunk as $position => $row) {
                $rowNumber = $offset + $position + 2;
                $message = $this->importRow($import, $mapper, $saveCollectionItem, $row);

                if ($message === null) {
                    $imported++;
                } elseif (count($failures) < Import::MAX_STORED_FAILURES) {
                    $failures[] = ['row' => $rowNumber, 'message' => $message];
                }
            }

            $updated = Import::query()
                ->whereKey($import->id)
                ->where('processed_rows', $offset)
                ->where('status', ImportStatus::Processing)
                ->update([
                    'processed_rows' => $offset + count($chunk),
                    'imported_rows' => $import->imported_rows + $imported,
                    'failures' => json_encode($failures),
                    'updated_at' => now(),
                ]);

            if ($updated === 0) {
                throw new ImportProgressConflict;
            }

            $import->forceFill([
                'processed_rows' => $offset + count($chunk),
                'imported_rows' => $import->imported_rows + $imported,
                'failures' => $failures,
            ])->syncOriginal();
        });
    }

    /**
     * Save one row (and any extra copies). Returns why it failed, or null on success.
     *
     * @param  list<string>  $row
     */
    private function importRow(Import $import, ImportRowMapper $mapper, SaveCollectionItem $saveCollectionItem, array $row): ?string
    {
        try {
            DB::transaction(function () use ($import, $mapper, $saveCollectionItem, $row): void {
                ['data' => $data, 'quantity' => $quantity] = $mapper->map($row);

                for ($copy = 0; $copy < $quantity; $copy++) {
                    $item = $saveCollectionItem->handle($import->user, $data);
                    $data['card_id'] = $item->card_id;
                    $data['parallel_id'] = $item->parallel_id;
                }
            });
        } catch (InvalidImportRow $exception) {
            return $exception->getMessage();
        } catch (ValidationException $exception) {
            return (string) collect($exception->errors())->flatten()->first();
        } catch (Throwable $exception) {
            report($exception);

            return 'This row couldn’t be saved.';
        }

        return null;
    }
}
