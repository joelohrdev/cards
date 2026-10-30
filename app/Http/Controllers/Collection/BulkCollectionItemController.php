<?php

namespace App\Http\Controllers\Collection;

use App\Actions\Collection\SaveCollectionItem;
use App\Enums\ItemStatus;
use App\Http\Controllers\Controller;
use App\Models\CollectionItem;
use App\Models\ItemImage;
use App\Models\Tag;
use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class BulkCollectionItemController extends Controller
{
    /**
     * Apply one action to many selected cards at once.
     */
    public function __invoke(Request $request, SaveCollectionItem $saveCollectionItem): RedirectResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1', 'max:1000'],
            'ids.*' => ['integer'],
            'action' => ['required', Rule::in(['tag', 'untag', 'move', 'status', 'value', 'delete'])],
            'tags' => ['required_if:action,tag,untag', 'array'],
            'tags.*' => ['string', 'max:50'],
            'storage_location_id' => ['nullable', 'integer', Rule::exists('storage_locations', 'id')],
            'status' => ['required_if:action,status', 'nullable', Rule::enum(ItemStatus::class)],
            'estimated_value' => ['required_if:action,value', 'nullable', 'numeric', 'min:0'],
        ]);

        /** @var 'tag'|'untag'|'move'|'status'|'value'|'delete' $action */
        $action = $validated['action'];
        $items = CollectionItem::query()->whereIn('id', $validated['ids']);
        $count = (clone $items)->count();

        DB::transaction(function () use ($action, $validated, $items, $user, $saveCollectionItem): void {
            match ($action) {
                'tag' => $this->tag($items->get(), $saveCollectionItem->resolveTagIds($user, $validated['tags'])),
                'untag' => $this->untag($items->get(), Tag::query()->whereIn('name', $validated['tags'])->get()),
                'move' => $items->update(['storage_location_id' => $validated['storage_location_id'] ?? null]),
                'status' => $items->update(['status' => $validated['status']]),
                'value' => $items->update(['estimated_value' => Money::toCents($validated['estimated_value']), 'value_updated_at' => now()]),
                'delete' => $this->delete($items),
            };
        });

        $verb = match ($action) {
            'tag' => 'Tagged',
            'untag' => 'Removed tags from',
            'move' => 'Moved',
            'status' => 'Updated status for',
            'value' => 'Set value for',
            'delete' => 'Deleted',
        };

        Inertia::flash('toast', ['type' => 'success', 'message' => "{$verb} {$count} ".str('card')->plural($count).'.']);

        return back();
    }

    /**
     * @param  Collection<int, CollectionItem>  $items
     * @param  list<int>  $tagIds
     */
    private function tag(Collection $items, array $tagIds): void
    {
        foreach ($items as $item) {
            $item->tags()->syncWithoutDetaching($tagIds);
        }
    }

    /**
     * @param  Collection<int, CollectionItem>  $items
     * @param  Collection<int, Tag>  $tags
     */
    private function untag(Collection $items, Collection $tags): void
    {
        foreach ($items as $item) {
            $item->tags()->detach($tags);
        }
    }

    /**
     * Delete the cards and their stored photos.
     *
     * @param  Builder<CollectionItem>  $items
     */
    private function delete(Builder $items): void
    {
        $paths = ItemImage::query()->whereIn('collection_item_id', (clone $items)->select('id'))->pluck('path')->all();
        $items->delete();
        Storage::disk(ItemImage::DISK)->delete($paths);
    }
}
