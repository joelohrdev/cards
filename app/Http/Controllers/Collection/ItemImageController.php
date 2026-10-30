<?php

namespace App\Http\Controllers\Collection;

use App\Enums\ImageSide;
use App\Http\Controllers\Controller;
use App\Models\CollectionItem;
use App\Models\ItemImage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class ItemImageController extends Controller
{
    /**
     * Upload photos of a card.
     */
    public function store(Request $request, CollectionItem $item): RedirectResponse
    {
        $validated = $request->validate([
            'images' => ['required', 'array', 'min:1', 'max:6'],
            'images.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:12288'],
            'side' => ['nullable', Rule::enum(ImageSide::class)],
        ]);

        $position = (int) $item->images()->max('position');
        $existing = $item->images()->count();

        foreach ($validated['images'] as $index => $file) {
            $side = $validated['side'] ?? match ($existing + $index) {
                0 => ImageSide::Front->value,
                1 => ImageSide::Back->value,
                default => ImageSide::Other->value,
            };

            $item->images()->create([
                'path' => $file->store('cards/'.$item->user_id, ItemImage::DISK),
                'side' => $side,
                'position' => $existing === 0 && $index === 0 ? 0 : ++$position,
            ]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Photos uploaded.']);

        return back();
    }

    /**
     * Make an image the primary (front) photo.
     */
    public function update(CollectionItem $item, ItemImage $image): RedirectResponse
    {
        abort_unless($image->collection_item_id === $item->id, 404);

        $item->images()->whereKeyNot($image->id)->increment('position');
        $image->update(['position' => 0]);

        return back();
    }

    /**
     * Delete a photo.
     */
    public function destroy(CollectionItem $item, ItemImage $image): RedirectResponse
    {
        abort_unless($image->collection_item_id === $item->id, 404);

        Storage::disk(ItemImage::DISK)->delete($image->path);
        $image->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Photo deleted.']);

        return back();
    }
}
