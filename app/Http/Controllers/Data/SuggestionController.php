<?php

namespace App\Http\Controllers\Data;

use App\Http\Controllers\Controller;
use App\Models\Parallel;
use App\Support\ExistingNames;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Values already used in a free-text field that works like a category (tags, where a card was
 * bought, teams…), so people pick the same spelling instead of typing a near-duplicate.
 */
class SuggestionController extends Controller
{
    /**
     * Fields that can be looked up: every ExistingNames field, plus a set's parallels.
     *
     * @return list<string>
     */
    public static function fields(): array
    {
        return [...array_keys(ExistingNames::FIELDS), 'parallel'];
    }

    /**
     * Up to 10 values containing what was typed: ones that start with it first, then the most
     * used. With nothing typed, the most used. `close` holds near misses ("Did you mean…?") when
     * nothing contains what was typed.
     */
    public function __invoke(Request $request, string $field, ExistingNames $existingNames): JsonResponse
    {
        $typed = trim((string) $request->string('q'));

        if ($field === 'parallel') {
            return response()->json(['suggestions' => $this->parallels($request->integer('set'), $typed), 'close' => []]);
        }

        [$table, $column] = ExistingNames::FIELDS[$field];
        $lowered = mb_strtolower($typed);

        $suggestions = DB::table($table)
            ->select($column)
            ->selectRaw('count(*) as uses')
            ->whereNotNull($column)
            ->where($column, '!=', '')
            ->when($lowered !== '', fn ($query) => $query->whereRaw("lower({$column}) like ?", ['%'.$lowered.'%']))
            ->groupBy($column)
            ->when($lowered !== '', fn ($query) => $query->orderByRaw("case when lower({$column}) like ? then 0 else 1 end", [$lowered.'%']))
            ->orderByDesc('uses')
            ->orderBy($column)
            ->limit(20)
            ->pluck($column)
            ->map(fn (mixed $value): string => (string) $value)
            ->unique(fn (string $value): string => mb_strtolower($value))
            ->take(10)
            ->values();

        $close = $suggestions->isEmpty() && $typed !== ''
            ? $existingNames->nearMisses($field, $typed, $request->filled('sport') ? (string) $request->string('sport') : null)
            : [];

        return response()->json(['suggestions' => $suggestions, 'close' => $close]);
    }

    /**
     * A set's parallels containing what was typed, starting-with matches first.
     *
     * @return Collection<int, string>
     */
    private function parallels(int $setId, string $typed): Collection
    {
        if ($setId === 0) {
            return collect();
        }

        $lowered = mb_strtolower($typed);

        return Parallel::query()
            ->where('card_set_id', $setId)
            ->orderBy('name')
            ->pluck('name')
            ->filter(fn (string $name): bool => $lowered === '' || str_contains(mb_strtolower($name), $lowered))
            ->sortBy(fn (string $name): int => str_starts_with(mb_strtolower($name), $lowered) ? 0 : 1)
            ->take(10)
            ->values();
    }
}
