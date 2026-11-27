<?php

namespace App\Http\Controllers\Selling;

use App\Actions\Grading\SyncGradingItemStatuses;
use App\Http\Controllers\Controller;
use App\Models\GradingSubmission;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/**
 * Moves a submission one step along (sent, received, grading, shipped back) with one tap,
 * filling in that milestone's date.
 */
class GradingProgressController extends Controller
{
    /**
     * The request names the status it expects to move to, so a double tap can't skip a step.
     */
    public function store(Request $request, GradingSubmission $submission, SyncGradingItemStatuses $syncStatuses): RedirectResponse
    {
        $next = $submission->status->next();

        $validated = $request->validate([
            'status' => ['required', Rule::in($next === null ? [] : [$next->value])],
        ], [
            'status.in' => $next === null
                ? 'This submission is waiting on its results. Use “Record results”.'
                : 'This submission has already moved on. Refresh to see where it is.',
        ]);

        DB::transaction(function () use ($submission, $validated, $syncStatuses): void {
            $submission->status = $validated['status'];
            $submission->stampMilestoneDates();
            $submission->save();
            $syncStatuses->handle($submission);
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => "Marked “{$submission->status->label()}”."]);

        return back();
    }
}
