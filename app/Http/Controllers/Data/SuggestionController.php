<?php

namespace App\Http\Controllers\Data;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Values already used in a free-text field that works like a category (tags, where a card was
 * bought, teams…), so people pick the same spelling instead of typing a near-duplicate.
 */
class SuggestionController extends Controller
{
    /**
     * Where each field's values live, as [table, column].
     *
     * @var array<string, array{0: string, 1: string}>
     */
    public const array FIELDS = [
        'tag' => ['tags', 'name'],
        'purchase_source' => ['collection_items', 'purchase_source'],
        'manufacturer' => ['manufacturers', 'name'],
        'player' => ['players', 'name'],
        'team' => ['teams', 'name'],
        'subset' => ['cards', 'subset'],
        'service_level' => ['grading_submissions', 'service_level'],
        'buyer' => ['sales', 'buyer'],
        'comp_source' => ['price_comps', 'source'],
    ];

    /**
     * Up to 10 values containing what was typed: ones that start with it first, then the most used.
     * With nothing typed, the most used values.
     */
    public function __invoke(Request $request, string $field): JsonResponse
    {
        abort_unless(array_key_exists($field, self::FIELDS), 404);

        [$table, $column] = self::FIELDS[$field];
        $typed = mb_strtolower(trim((string) $request->string('q')));

        $values = DB::table($table)
            ->select($column)
            ->selectRaw('count(*) as uses')
            ->whereNotNull($column)
            ->where($column, '!=', '')
            ->when($typed !== '', fn ($query) => $query->whereRaw("lower({$column}) like ?", ['%'.$typed.'%']))
            ->groupBy($column)
            ->when($typed !== '', fn ($query) => $query->orderByRaw("case when lower({$column}) like ? then 0 else 1 end", [$typed.'%']))
            ->orderByDesc('uses')
            ->orderBy($column)
            ->limit(20)
            ->pluck($column)
            ->map(fn (mixed $value): string => (string) $value)
            ->unique(fn (string $value): string => mb_strtolower($value))
            ->take(10)
            ->values();

        return response()->json(['suggestions' => $values]);
    }
}
