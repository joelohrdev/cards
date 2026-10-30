<?php

namespace App\Http\Controllers\Catalog;

use App\Http\Controllers\Controller;
use App\Http\Resources\CardSetResource;
use App\Models\Card;
use App\Models\CardSet;
use App\Support\CatalogCardSearch;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CatalogLookupController extends Controller
{
    /**
     * Find checklist cards for the card picker, e.g. "1986 fleer jordan" or "prizm #1".
     * With only a set and no words, returns that set's whole checklist.
     */
    public function cards(Request $request, CatalogCardSearch $catalogCardSearch): JsonResponse
    {
        $words = CatalogCardSearch::words((string) $request->string('q'));
        $setId = $request->integer('set') ?: null;

        if ($words === [] && $setId === null) {
            return response()->json(['cards' => []]);
        }

        return response()->json([
            'cards' => $catalogCardSearch->search($words, $setId, $words === [] ? null : 25)->map(fn (Card $card): array => CatalogCardSearch::present($card)),
        ]);
    }

    /**
     * Find sets for set pickers.
     */
    public function sets(Request $request): JsonResponse
    {
        $words = array_values(array_filter(explode(' ', trim((string) $request->string('q')))));

        $sets = CardSet::query()
            ->with(['manufacturer', 'parallels'])
            ->where(function (Builder $sets) use ($words): void {
                foreach ($words as $word) {
                    $sets->where(fn (Builder $set) => $set->whereLike('name', "%{$word}%")
                        ->orWhere('year', $word)
                        ->orWhere('season', $word)
                        ->orWhereHas('manufacturer', fn (Builder $manufacturer) => $manufacturer->whereLike('name', "%{$word}%")));
                }
            })
            ->orderByDesc('year')
            ->orderBy('name')
            ->limit(20)
            ->get();

        return response()->json([
            'sets' => $sets->map(fn (CardSet $set): array => [
                ...CardSetResource::make($set)->resolve(),
                'parallels' => $set->parallels->sortBy('name')->map->only(['id', 'name', 'print_run'])->values(),
            ]),
        ]);
    }
}
