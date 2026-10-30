<?php

namespace App\Http\Controllers\Selling;

use App\Actions\Grading\AddItemsToGradingSubmission;
use App\Actions\Grading\SyncGradingItemStatuses;
use App\Enums\Grader;
use App\Enums\GradingStatus;
use App\Enums\ItemStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Selling\GradingSubmissionRequest;
use App\Http\Resources\CollectionItemResource;
use App\Models\CollectionItem;
use App\Models\GradingSubmission;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class GradingSubmissionController extends Controller
{
    /**
     * List submissions, open first, with fee and turnaround stats.
     */
    public function index(Request $request): Response
    {
        $submissions = GradingSubmission::query()
            ->withCount('collectionItems')
            ->withSum('collectionItems as fee_total', 'grading_submission_items.fee')
            ->withSum('collectionItems as declared_total', 'grading_submission_items.declared_value')
            ->latest()
            ->latest('id')
            ->get();

        [$completed, $open] = $submissions->partition(fn (GradingSubmission $submission): bool => $submission->isCompleted());

        $turnarounds = $completed
            ->filter(fn (GradingSubmission $submission): bool => $submission->submitted_on !== null && $submission->returned_on !== null)
            ->map(fn (GradingSubmission $submission): int => (int) $submission->submitted_on->diffInDays($submission->returned_on));

        return Inertia::render('grading/index', [
            'open' => $open->map(fn (GradingSubmission $submission): array => self::summary($submission))->values(),
            'completed' => $completed->map(fn (GradingSubmission $submission): array => self::summary($submission))->values(),
            'stats' => [
                'open_count' => $open->count(),
                'completed_count' => $completed->count(),
                'cards_out' => (int) $open->filter(fn (GradingSubmission $submission): bool => $submission->isWithGrader())->sum('collection_items_count'),
                'total_fees' => (int) $submissions->sum(fn (GradingSubmission $submission): int => (int) $submission->fee_total + $submission->shipping_cost),
                'average_turnaround_days' => $turnarounds->isEmpty() ? null : (int) round($turnarounds->avg()),
            ],
        ]);
    }

    /**
     * Show the new-submission form.
     */
    public function create(): Response
    {
        return Inertia::render('grading/create', [
            'graders' => Grader::options(),
        ]);
    }

    /**
     * Start a submission, optionally with cards already in it.
     */
    public function store(GradingSubmissionRequest $request, AddItemsToGradingSubmission $addItems, SyncGradingItemStatuses $syncStatuses): RedirectResponse
    {
        $validated = $request->validated();

        $submission = DB::transaction(function () use ($request, $validated, $addItems, $syncStatuses): GradingSubmission {
            $submission = $request->user()->gradingSubmissions()->create([
                ...collect($validated)->except('collection_item_ids')->all(),
                'status' => $validated['status'] ?? GradingStatus::Preparing->value,
                'shipping_cost' => Money::toCents($validated['shipping_cost'] ?? null) ?? 0,
            ]);

            $itemIds = $validated['collection_item_ids'] ?? [];

            if ($itemIds !== []) {
                $addItems->handle($submission, CollectionItem::query()->whereKey($itemIds)->get());
            }

            $syncStatuses->handle($submission);

            return $submission;
        });

        $count = count($validated['collection_item_ids'] ?? []);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $count > 0
                ? "Started a {$submission->grader->value} submission with {$count} ".str('card')->plural($count).'.'
                : "Started a {$submission->grader->value} submission. Add the cards you’re sending.",
        ]);

        return to_route('grading.show', $submission);
    }

    /**
     * Show a submission with its cards.
     */
    public function show(GradingSubmission $submission): Response
    {
        $items = $submission->collectionItems()
            ->with(CollectionItemResource::LIST_RELATIONS)
            ->orderByPivot('id')
            ->get();

        return Inertia::render('grading/show', [
            'submission' => [
                ...self::summary($submission),
                'notes' => $submission->notes,
                'is_completed' => $submission->isCompleted(),
                'is_with_grader' => $submission->isWithGrader(),
            ],
            'items' => $items->map(fn (CollectionItem $item): array => [
                'item' => CollectionItemResource::make($item)->resolve(),
                'declared_value' => $item->getRelation('pivot')->declared_value,
                'fee' => (int) $item->getRelation('pivot')->fee,
                'resulting_grade' => $item->getRelation('pivot')->resulting_grade,
                'resulting_cert_number' => $item->getRelation('pivot')->resulting_cert_number,
            ]),
            'totals' => [
                'declared' => (int) $items->sum(fn (CollectionItem $item): int => (int) $item->getRelation('pivot')->declared_value),
                'fees' => (int) $items->sum(fn (CollectionItem $item): int => (int) $item->getRelation('pivot')->fee),
                'shipping' => $submission->shipping_cost,
            ],
            'graders' => Grader::options(),
            'statuses' => array_values(array_filter(
                GradingStatus::options(),
                fn (array $option): bool => $option['value'] !== GradingStatus::Completed->value,
            )),
        ]);
    }

    /**
     * Update a submission's details and status. Its cards follow the status.
     */
    public function update(GradingSubmissionRequest $request, GradingSubmission $submission, SyncGradingItemStatuses $syncStatuses): RedirectResponse
    {
        $validated = $request->validated();
        $attributes = [
            ...$validated,
            'shipping_cost' => Money::toCents($validated['shipping_cost'] ?? null) ?? 0,
        ];

        if ($submission->isCompleted() || ! isset($validated['status'])) {
            unset($attributes['status']);
        }

        if (isset($attributes['status']) && $attributes['status'] !== GradingStatus::Preparing->value && blank($attributes['submitted_on'] ?? null) && $submission->submitted_on === null) {
            $attributes['submitted_on'] = now()->toDateString();
        }

        DB::transaction(function () use ($submission, $attributes, $syncStatuses): void {
            $submission->update($attributes);
            $syncStatuses->handle($submission);
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Submission updated.']);

        return back();
    }

    /**
     * Delete a submission. Cards still out at the grader go back to "Owned".
     */
    public function destroy(GradingSubmission $submission): RedirectResponse
    {
        DB::transaction(function () use ($submission): void {
            CollectionItem::query()
                ->whereKey($submission->collectionItems()->pluck('collection_items.id'))
                ->where('status', ItemStatus::AtGrading->value)
                ->update(['status' => ItemStatus::Owned->value]);

            $submission->delete();
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Submission deleted.']);

        return to_route('grading.index');
    }

    /**
     * @return array<string, mixed>
     */
    private static function summary(GradingSubmission $submission): array
    {
        return [
            'id' => $submission->id,
            'grader' => $submission->grader->value,
            'service_level' => $submission->service_level,
            'reference' => $submission->reference,
            'status' => $submission->status->value,
            'status_label' => $submission->status->label(),
            'submitted_on' => $submission->submitted_on?->toDateString(),
            'returned_on' => $submission->returned_on?->toDateString(),
            'shipping_cost' => $submission->shipping_cost,
            'items_count' => $submission->collection_items_count ?? $submission->collectionItems()->count(),
            'fee_total' => isset($submission->fee_total) ? (int) $submission->fee_total : null,
            'declared_total' => isset($submission->declared_total) ? (int) $submission->declared_total : null,
            'turnaround_days' => $submission->submitted_on && $submission->returned_on
                ? (int) $submission->submitted_on->diffInDays($submission->returned_on)
                : null,
            'created_at' => $submission->created_at->toIso8601String(),
        ];
    }
}
