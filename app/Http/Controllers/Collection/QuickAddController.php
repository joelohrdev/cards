<?php

namespace App\Http\Controllers\Collection;

use App\Actions\Catalog\ResolveCard;
use App\Actions\Collection\SaveCollectionItem;
use App\Enums\Grader;
use App\Enums\RawCondition;
use App\Http\Controllers\Controller;
use App\Models\CardSet;
use App\Support\FormOptions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class QuickAddController extends Controller
{
    /**
     * Show the rapid-entry form for typing in a stack of cards from one set.
     */
    public function create(Request $request): Response
    {
        return Inertia::render('collection/quick-add', [
            'options' => FormOptions::for(),
        ]);
    }

    /**
     * Add every entered row to the collection.
     */
    public function store(Request $request, ResolveCard $resolveCard, SaveCollectionItem $saveCollectionItem): RedirectResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'card_set_id' => ['required', 'integer', 'exists:card_sets,id'],
            'storage_location_id' => ['nullable', 'integer', Rule::exists('storage_locations', 'id')],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['string', 'max:50'],
            'rows' => ['required', 'array', 'min:1', 'max:500'],
            'rows.*.number' => ['required', 'string', 'max:30'],
            'rows.*.name' => ['nullable', 'string', 'max:200'],
            'rows.*.parallel_id' => ['nullable', 'integer', Rule::exists('parallels', 'id')->where('card_set_id', $request->integer('card_set_id'))],
            'rows.*.raw_condition' => ['nullable', Rule::enum(RawCondition::class)],
            'rows.*.grader' => ['nullable', Rule::enum(Grader::class)],
            'rows.*.grade' => ['nullable', 'required_with:rows.*.grader', 'string', 'max:20'],
            'rows.*.purchase_price' => ['nullable', 'numeric', 'min:0'],
            'rows.*.estimated_value' => ['nullable', 'numeric', 'min:0'],
            'rows.*.quantity' => ['nullable', 'integer', 'min:1', 'max:100'],
        ], [
            'rows.*.number.required' => 'Every row needs a card number.',
        ]);

        $set = CardSet::query()->findOrFail((int) $validated['card_set_id']);
        $added = 0;

        DB::transaction(function () use ($validated, $set, $user, $resolveCard, $saveCollectionItem, &$added): void {
            foreach ($validated['rows'] as $row) {
                $card = $resolveCard->handle($set, ['number' => $row['number'], 'name' => $row['name'] ?? null]);

                for ($copy = 0; $copy < (int) ($row['quantity'] ?? 1); $copy++) {
                    $saveCollectionItem->handle($user, [
                        'card_id' => $card->id,
                        'parallel_id' => $row['parallel_id'] ?? null,
                        'is_graded' => filled($row['grader'] ?? null),
                        'grader' => $row['grader'] ?? null,
                        'grade' => $row['grade'] ?? null,
                        'raw_condition' => $row['raw_condition'] ?? null,
                        'purchase_price' => $row['purchase_price'] ?? null,
                        'estimated_value' => $row['estimated_value'] ?? null,
                        'storage_location_id' => $validated['storage_location_id'] ?? null,
                        'tags' => $validated['tags'] ?? [],
                    ]);
                    $added++;
                }
            }
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => "Added {$added} ".str('card')->plural($added)." from {$set->full_name}."]);

        return to_route('collection.quick-add');
    }
}
