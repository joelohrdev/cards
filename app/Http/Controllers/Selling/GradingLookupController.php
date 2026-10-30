<?php

namespace App\Http\Controllers\Selling;

use App\Enums\GradingStatus;
use App\Enums\ItemStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\CollectionItemResource;
use App\Models\CollectionItem;
use App\Models\GradingSubmission;
use App\Support\CollectionItemSearch;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * JSON lookups for the grading pickers.
 */
class GradingLookupController extends Controller
{
    /**
     * Raw, in-hand cards that can go into a submission, e.g. "jordan 1986".
     */
    public function candidates(Request $request): JsonResponse
    {
        $search = new CollectionItemSearch((string) $request->string('q'), [
            'status' => [ItemStatus::Owned->value],
            'graded' => 'raw',
            'sort' => 'value_desc',
        ]);

        $items = $search->builder()
            ->whereDoesntHave('gradingSubmissions', fn (Builder $submissions) => $submissions->where('grading_submissions.status', '!=', GradingStatus::Completed->value))
            ->with(CollectionItemResource::LIST_RELATIONS)
            ->limit(20)
            ->get();

        return response()->json([
            'items' => $items->map(fn (CollectionItem $item): array => CollectionItemResource::make($item)->resolve()),
        ]);
    }

    /**
     * Submissions still being prepared, which cards can be added to.
     */
    public function preparing(Request $request): JsonResponse
    {
        $submissions = GradingSubmission::query()
            ->where('status', GradingStatus::Preparing)
            ->withCount('collectionItems')
            ->latest()
            ->get();

        return response()->json([
            'submissions' => $submissions->map(fn (GradingSubmission $submission): array => [
                'id' => $submission->id,
                'grader' => $submission->grader->value,
                'service_level' => $submission->service_level,
                'reference' => $submission->reference,
                'items_count' => $submission->collection_items_count,
                'created_at' => $submission->created_at->toIso8601String(),
            ]),
        ]);
    }
}
