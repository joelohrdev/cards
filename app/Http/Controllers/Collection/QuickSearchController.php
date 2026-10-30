<?php

namespace App\Http\Controllers\Collection;

use App\Http\Controllers\Controller;
use App\Http\Resources\CollectionItemResource;
use App\Models\CardSet;
use App\Models\CollectionItem;
use App\Models\Player;
use App\Support\CollectionItemSearch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class QuickSearchController extends Controller
{
    /**
     * Fast results for the global search palette.
     */
    public function __invoke(Request $request): JsonResponse
    {
        $query = trim((string) $request->string('q'));

        if (mb_strlen($query) < 2) {
            return response()->json(['items' => [], 'sets' => [], 'players' => []]);
        }

        $search = new CollectionItemSearch($query, ['status' => ['all']]);
        $builder = $search->builder();
        $total = (clone $builder)->toBase()->reorder()->count();

        $items = $builder
            ->with(CollectionItemResource::LIST_RELATIONS)
            ->limit(8)
            ->get()
            ->map(fn (CollectionItem $item): array => CollectionItemResource::make($item)->resolve());

        $sets = CardSet::query()
            ->with('manufacturer')
            ->where(function ($sets) use ($query): void {
                foreach (array_filter(explode(' ', $query)) as $word) {
                    $sets->where(fn ($set) => $set->whereLike('name', "%{$word}%")
                        ->orWhere('year', $word)
                        ->orWhereHas('manufacturer', fn ($manufacturer) => $manufacturer->whereLike('name', "%{$word}%")));
                }
            })
            ->orderByDesc('year')
            ->limit(5)
            ->get()
            ->map(fn (CardSet $set): array => ['id' => $set->id, 'name' => $set->full_name, 'sport' => $set->sport->label()]);

        $players = Player::query()
            ->whereLike('name', "%{$query}%")
            ->orderBy('name')
            ->limit(5)
            ->get(['id', 'name', 'sport'])
            ->map(fn (Player $player): array => ['id' => $player->id, 'name' => $player->name, 'sport' => $player->sport->label()]);

        return response()->json([
            'items' => $items,
            'total' => $total,
            'sets' => $sets,
            'players' => $players,
        ]);
    }
}
