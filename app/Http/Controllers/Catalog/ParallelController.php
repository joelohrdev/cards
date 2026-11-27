<?php

namespace App\Http\Controllers\Catalog;

use App\Http\Controllers\Controller;
use App\Models\CardSet;
use App\Models\Parallel;
use App\Support\ExistingNames;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;

class ParallelController extends Controller
{
    /**
     * Add a parallel to a set.
     */
    public function store(Request $request, CardSet $set, ExistingNames $existingNames): RedirectResponse
    {
        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:100',
                function (string $attribute, mixed $value, Closure $fail) use ($set, $existingNames): void {
                    $existing = $existingNames->parallel($set->id, (string) $value);

                    if ($existing !== null) {
                        $fail("This set already has the “{$existing->name}” parallel.");
                    }
                },
            ],
            'print_run' => ['nullable', 'integer', 'min:1'],
        ]);
        $validated['name'] = Str::squish($validated['name']);

        $set->parallels()->create($validated);

        Inertia::flash('toast', ['type' => 'success', 'message' => "Added the {$validated['name']} parallel."]);

        return back();
    }

    /**
     * Remove a parallel. Owned copies fall back to the base card.
     */
    public function destroy(CardSet $set, Parallel $parallel): RedirectResponse
    {
        abort_unless($parallel->card_set_id === $set->id, 404);

        $parallel->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Parallel removed.']);

        return back();
    }
}
