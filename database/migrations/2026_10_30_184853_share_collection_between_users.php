<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Everyone signed in works from one shared collection. user_id now records who added a
 * record and survives that person's account being removed; per-user tags, want-list
 * entries and value snapshots are merged into single shared ones.
 */
return new class extends Migration
{
    /**
     * Tables whose user_id becomes an optional "added by" reference.
     *
     * @var list<string>
     */
    private const array ADDED_BY_TABLES = [
        'collection_items',
        'storage_locations',
        'tags',
        'price_comps',
        'want_list_entries',
        'grading_submissions',
        'sales',
        'ebay_listings',
        'imports',
        'share_links',
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $this->mergeDuplicateTags();
        $this->mergeDuplicateWants();
        $this->mergeValueSnapshots();

        Schema::table('tags', function (Blueprint $table) {
            $table->dropUnique(['user_id', 'name']);
        });

        Schema::table('want_list_entries', function (Blueprint $table) {
            $table->dropUnique(['user_id', 'card_id', 'parallel_id']);
        });

        foreach (self::ADDED_BY_TABLES as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropForeign(['user_id']);
            });

            Schema::table($tableName, function (Blueprint $table) {
                $table->foreignId('user_id')->nullable()->change();
                $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
            });
        }

        Schema::table('tags', function (Blueprint $table) {
            $table->unique('name');
        });

        Schema::table('want_list_entries', function (Blueprint $table) {
            $table->unique(['card_id', 'parallel_id']);
        });

        Schema::table('value_snapshots', function (Blueprint $table) {
            $table->dropUnique(['user_id', 'captured_on']);
            $table->dropForeign(['user_id']);
        });

        Schema::table('value_snapshots', function (Blueprint $table) {
            $table->dropColumn('user_id');
            $table->unique('captured_on');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Merged tags, wants and snapshots can't be split back out per user.
    }

    /**
     * Fold same-named tags into the oldest one, carrying over their cards, share links and imports.
     */
    private function mergeDuplicateTags(): void
    {
        $keepers = DB::table('tags')
            ->selectRaw('name, min(id) as keep_id')
            ->groupBy('name')
            ->havingRaw('count(*) > 1')
            ->get();

        foreach ($keepers as $keeper) {
            $duplicateIds = DB::table('tags')
                ->where('name', $keeper->name)
                ->where('id', '!=', $keeper->keep_id)
                ->pluck('id');

            $itemIds = DB::table('collection_item_tag')
                ->whereIn('tag_id', $duplicateIds)
                ->pluck('collection_item_id')
                ->unique();

            DB::table('collection_item_tag')->insertOrIgnore(
                $itemIds->map(fn (int $itemId): array => [
                    'collection_item_id' => $itemId,
                    'tag_id' => $keeper->keep_id,
                ])->values()->all(),
            );

            DB::table('share_links')->whereIn('tag_id', $duplicateIds)->update(['tag_id' => $keeper->keep_id]);
            DB::table('imports')->whereIn('tag_id', $duplicateIds)->update(['tag_id' => $keeper->keep_id]);
            DB::table('tags')->whereIn('id', $duplicateIds)->delete();
        }
    }

    /**
     * Keep the oldest want-list entry for each card and parallel.
     */
    private function mergeDuplicateWants(): void
    {
        $keepIds = DB::table('want_list_entries')
            ->selectRaw('min(id) as keep_id')
            ->groupBy('card_id', 'parallel_id')
            ->pluck('keep_id');

        DB::table('want_list_entries')->whereNotIn('id', $keepIds)->delete();
    }

    /**
     * Sum each day's per-user snapshots into one shared snapshot.
     */
    private function mergeValueSnapshots(): void
    {
        $days = DB::table('value_snapshots')
            ->selectRaw('captured_on, min(id) as keep_id, sum(item_count) as item_count, sum(total_value) as total_value, sum(total_cost) as total_cost')
            ->groupBy('captured_on')
            ->get();

        foreach ($days as $day) {
            DB::table('value_snapshots')->where('id', $day->keep_id)->update([
                'item_count' => $day->item_count,
                'total_value' => $day->total_value,
                'total_cost' => $day->total_cost,
            ]);

            DB::table('value_snapshots')
                ->where('captured_on', $day->captured_on)
                ->where('id', '!=', $day->keep_id)
                ->delete();
        }
    }
};
