<?php

namespace App\Models;

use App\Enums\Grader;
use App\Enums\GradingStatus;
use App\Enums\ShippingCarrier;
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
 * @property ShippingCarrier|null $outbound_carrier
 * @property string|null $outbound_tracking
 * @property CarbonImmutable|null $received_on
 * @property CarbonImmutable|null $shipped_back_on
 * @property ShippingCarrier|null $return_carrier
 * @property string|null $return_tracking
 * @property CarbonImmutable|null $returned_on
 * @property int $shipping_cost
 * @property string|null $notes
 * @property-read int|null $fee_total
 * @property-read int $collection_items_count
 * @property CarbonImmutable $created_at
 */
#[Fillable(['grader', 'service_level', 'reference', 'status', 'submitted_on', 'outbound_carrier', 'outbound_tracking', 'received_on', 'shipped_back_on', 'return_carrier', 'return_tracking', 'returned_on', 'shipping_cost', 'notes'])]
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
            'outbound_carrier' => ShippingCarrier::class,
            'received_on' => 'date',
            'shipped_back_on' => 'date',
            'return_carrier' => ShippingCarrier::class,
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

    /**
     * Fill in today's date for the milestone the submission just reached, unless one was entered.
     * Any status past "Going to send" means it was sent, so the sent date is filled in too.
     */
    public function stampMilestoneDates(): void
    {
        $today = now()->startOfDay();

        if ($this->status !== GradingStatus::Preparing && $this->submitted_on === null) {
            $this->submitted_on = $today;
        }

        if (! $this->isDirty('status')) {
            return;
        }

        if ($this->status === GradingStatus::Received && $this->received_on === null) {
            $this->received_on = $today;
        }

        if ($this->status === GradingStatus::Shipped && $this->shipped_back_on === null) {
            $this->shipped_back_on = $today;
        }
    }

    /**
     * Where the submission is, for the progress timeline: status, milestone dates, both
     * shipments with tracking links, and how many days the cards have been out.
     *
     * @return array{status: string, status_label: string, submitted_on: string|null, received_on: string|null, shipped_back_on: string|null, returned_on: string|null, outbound: array{carrier: string|null, carrier_label: string|null, tracking: string|null, tracking_url: string|null}, return: array{carrier: string|null, carrier_label: string|null, tracking: string|null, tracking_url: string|null}, days_out: int|null}
     */
    public function progress(): array
    {
        return [
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'submitted_on' => $this->submitted_on?->toDateString(),
            'received_on' => $this->received_on?->toDateString(),
            'shipped_back_on' => $this->shipped_back_on?->toDateString(),
            'returned_on' => $this->returned_on?->toDateString(),
            'outbound' => self::shipment($this->outbound_carrier, $this->outbound_tracking),
            'return' => self::shipment($this->return_carrier, $this->return_tracking),
            'days_out' => $this->isWithGrader() && $this->submitted_on !== null
                ? (int) $this->submitted_on->diffInDays(now()->startOfDay())
                : null,
        ];
    }

    /**
     * @return array{carrier: string|null, carrier_label: string|null, tracking: string|null, tracking_url: string|null}
     */
    private static function shipment(?ShippingCarrier $carrier, ?string $tracking): array
    {
        return [
            'carrier' => $carrier?->value,
            'carrier_label' => $carrier?->label(),
            'tracking' => $tracking,
            'tracking_url' => $carrier !== null && filled($tracking) ? $carrier->trackingUrl($tracking) : null,
        ];
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
