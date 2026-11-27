<?php

namespace App\Http\Controllers\Settings;

use App\Actions\Names\MergeDuplicateNames;
use App\Http\Controllers\Controller;
use App\Support\DuplicateNames;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Tidy up names: groups of near-duplicate players, teams, brands, sets, parallels, tags and
 * free-text values, each merged into one spelling. Admins only.
 */
class TidyNamesController extends Controller
{
    public function index(DuplicateNames $duplicateNames): Response
    {
        return Inertia::render('settings/tidy-names', [
            'groups' => $duplicateNames->groups(),
        ]);
    }

    /**
     * Merge one group into the spelling chosen to keep.
     */
    public function store(Request $request, MergeDuplicateNames $mergeDuplicateNames): RedirectResponse
    {
        $validated = $request->validate([
            'type' => ['required', Rule::in(array_keys(DuplicateNames::TYPES))],
            'keep' => ['required', 'string', 'max:255'],
            'merge' => ['required', 'array', 'min:1', 'max:50'],
            'merge.*' => ['string', 'max:255'],
        ]);

        $mergeDuplicateNames->handle($validated['type'], $validated['keep'], array_values($validated['merge']));

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Merged. Everything now uses one spelling.']);

        return back();
    }
}
