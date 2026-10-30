<?php

namespace App\Http\Controllers\Organize;

use App\Http\Controllers\Controller;
use App\Models\Tag;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class TagController extends Controller
{
    /**
     * List the tags with usage counts and value.
     */
    public function index(Request $request): Response
    {
        $tags = Tag::query()
            ->withCount('collectionItems')
            ->withSum('collectionItems', 'estimated_value')
            ->orderBy('name')
            ->get()
            ->map(fn (Tag $tag): array => [
                'id' => $tag->id,
                'name' => $tag->name,
                'color' => $tag->color,
                'items_count' => $tag->collection_items_count,
                'total_value' => (int) $tag->collection_items_sum_estimated_value,
            ]);

        return Inertia::render('organize/tags', [
            'tags' => $tags,
            'colors' => Tag::COLORS,
        ]);
    }

    /**
     * Create a tag.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:50', Rule::unique('tags')],
            'color' => ['required', Rule::in(Tag::COLORS)],
        ]);

        $request->user()->tags()->create($validated);

        Inertia::flash('toast', ['type' => 'success', 'message' => "Created the “{$validated['name']}” tag."]);

        return back();
    }

    /**
     * Rename or recolor a tag.
     */
    public function update(Request $request, Tag $tag): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:50', Rule::unique('tags')->ignore($tag->id)],
            'color' => ['required', Rule::in(Tag::COLORS)],
        ]);

        $tag->update($validated);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Tag updated.']);

        return back();
    }

    /**
     * Delete a tag. The cards themselves are untouched.
     */
    public function destroy(Tag $tag): RedirectResponse
    {
        $tag->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Tag deleted.']);

        return back();
    }
}
