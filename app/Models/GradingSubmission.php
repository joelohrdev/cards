<?php

namespace App\Models;

use App\Enums\Grader;
use App\Enums\GradingStatus;
use Carbon\CarbonImmutable;
use Database\Factories\GradingSubmissionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * @property int $id
 * @property int|null $user_id
 * @property Grader $grader
 * @property string|null $service_level
 * @property string|null $reference
 * @property GradingStatus $status
 * @property CarbonImmutable|null $submitted_on
 * @property CarbonImmutable|null $returned_on
 * @property int $shipping_cost
 * @property string|null $notes
 * @property-read int|null $fee_total
 * @property-read int $collection_items_count
 * @property CarbonImmutable $created_at
 */
#[Fillable(['grader', 'service_level', 'reference', 'status', 'submitted_on', 'returned_on', 'shipping_cost', 'notes'])]
class GradingSubmission extends Model
{
    /** @use HasFactory<GradingSubmissionFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'grader' => Grader::class,
            'status' => GradingStatus::class,
            'submitted_on' => 'date',
            'returned_on' => 'date',
            'shipping_cost' => 'integer',
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
     * @return BelongsToMany<CollectionItem, $this>
     */
    public function collectionItems(): BelongsToMany
    {
        return $this->belongsToMany(CollectionItem::class, 'grading_submission_items')
            ->withPivot(['id', 'declared_value', 'fee', 'resulting_grade', 'resulting_cert_number'])
            ->withTimestamps();
    }

    /**
     * Whether the cards have left the collection and are with the grader (or on their way back).
     */
    public function isWithGrader(): bool
    {
        return ! in_array($this->status, [GradingStatus::Preparing, GradingStatus::Completed], true);
    }

    public function isCompleted(): bool
    {
        return $this->status === GradingStatus::Completed;
    }

    /**
     * Limit the query to submissions that haven't been completed.
     *
     * @param  Builder<GradingSubmission>  $query
     */
    public function scopeOpen(Builder $query): void
    {
        $query->where('status', '!=', GradingStatus::Completed);
    }
}
