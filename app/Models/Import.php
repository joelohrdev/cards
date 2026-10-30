<?php

namespace App\Models;

use App\Enums\ImportStatus;
use Carbon\CarbonImmutable;
use Database\Factories\ImportFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A CSV upload of cards, mapped column-by-column into the collection.
 *
 * @property int $id
 * @property int|null $user_id
 * @property int|null $tag_id
 * @property string $original_filename
 * @property string $path
 * @property list<string> $headers
 * @property array<string, int|null>|null $mapping Import field key => zero-based column index.
 * @property array{sport?: string|null, tags?: list<string>, storage_location_id?: int|null}|null $defaults
 * @property ImportStatus $status
 * @property int $total_rows
 * @property int $processed_rows
 * @property int $imported_rows
 * @property list<array{row: int, message: string}>|null $failures
 * @property CarbonImmutable|null $completed_at
 * @property CarbonImmutable $created_at
 * @property-read User|null $user
 * @property-read Tag|null $tag
 */
#[Fillable(['original_filename', 'path', 'headers', 'mapping', 'defaults', 'status', 'total_rows', 'processed_rows', 'imported_rows', 'failures', 'completed_at'])]
class Import extends Model
{
    /** @use HasFactory<ImportFactory> */
    use HasFactory;

    /**
     * Uploaded files live on the private local disk.
     */
    public const string DISK = 'local';

    /**
     * Only the first failures are stored; the rest are counted.
     */
    public const int MAX_STORED_FAILURES = 500;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'headers' => 'array',
            'mapping' => 'array',
            'defaults' => 'array',
            'failures' => 'array',
            'status' => ImportStatus::class,
            'total_rows' => 'integer',
            'processed_rows' => 'integer',
            'imported_rows' => 'integer',
            'completed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The tag applied to every card this import created.
     *
     * @return BelongsTo<Tag, $this>
     */
    public function tag(): BelongsTo
    {
        return $this->belongsTo(Tag::class);
    }

    /**
     * Rows that were read but could not be imported.
     */
    public function failedRows(): int
    {
        return max(0, $this->processed_rows - $this->imported_rows);
    }

    public function isRunning(): bool
    {
        return $this->status === ImportStatus::Processing;
    }
}
