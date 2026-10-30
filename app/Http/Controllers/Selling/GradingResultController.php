<?php

namespace App\Http\Controllers\Selling;

use App\Actions\Grading\CompleteGradingSubmission;
use App\Http\Controllers\Controller;
use App\Models\GradingSubmission;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

/**
 * Records the grades that came back and completes the submission.
 */
class GradingResultController extends Controller
{
    public function store(Request $request, GradingSubmission $submission, CompleteGradingSubmission $complete): RedirectResponse
    {
        if ($submission->isCompleted()) {
            throw ValidationException::withMessages(['results' => 'These results are already recorded.']);
        }

        if (! $submission->collectionItems()->exists()) {
            throw ValidationException::withMessages(['results' => 'Add the cards in this submission before recording results.']);
        }

        $validated = $request->validate([
            'results' => ['required', 'array'],
            'results.*.grade' => ['nullable', 'string', 'max:20'],
            'results.*.cert_number' => ['nullable', 'string', 'max:50'],
            'add_costs_to_basis' => ['boolean'],
            'returned_on' => ['nullable', 'date', 'before_or_equal:today'],
        ], [
            'results.required' => 'Enter the grade for each card that came back.',
        ]);

        $complete->handle(
            $submission,
            $validated['results'],
            $request->boolean('add_costs_to_basis'),
            isset($validated['returned_on']) ? Carbon::parse($validated['returned_on']) : null,
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Results recorded. Your cards are back in the collection with their new grades.']);

        return back();
    }
}
