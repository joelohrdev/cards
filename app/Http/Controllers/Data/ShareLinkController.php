<?php

namespace App\Http\Controllers\Data;

use App\Http\Controllers\Controller;
use App\Http\Requests\Data\ShareLinkRequest;
use App\Models\CollectionItem;
use App\Models\ShareLink;
use App\Models\Tag;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ShareLinkController extends Controller
{
    /**
     * List the share links with the form to make a new one.
     */
    public function index(Request $request): Response
    {
        $inHandCount = CollectionItem::query()->inHand()->count();

        return Inertia::render('share-links/index', [
            'links' => ShareLink::query()
                ->with('tag')
                ->latest()
                ->get()
                ->map(fn (ShareLink $link): array => [
                    'id' => $link->id,
                    'title' => $link->title,
                    'description' => $link->description,
                    'url' => route('showcase', $link->token),
                    'tag' => $link->tag ? ['id' => $link->tag->id, 'name' => $link->tag->name, 'color' => $link->tag->color] : null,
                    'show_values' => $link->show_values,
                    'expires_at' => $link->expires_at?->toDateString(),
                    'is_expired' => $link->isExpired(),
                    'cards_count' => $link->tag ? $link->tag->collectionItems()->inHand()->count() : $inHandCount,
                    'created_at' => $link->created_at->toIso8601String(),
                ]),
            'tags' => Tag::query()->orderBy('name')->get(['id', 'name', 'color']),
        ]);
    }

    /**
     * Create a share link.
     */
    public function store(ShareLinkRequest $request): RedirectResponse
    {
        $request->user()->shareLinks()->create($request->shareLinkAttributes());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Share link created. Copy it to send it to someone.']);

        return to_route('share-links.index');
    }

    /**
     * Change what a share link shows. The URL stays the same.
     */
    public function update(ShareLinkRequest $request, ShareLink $shareLink): RedirectResponse
    {
        $shareLink->update($request->shareLinkAttributes());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Share link updated.']);

        return to_route('share-links.index');
    }

    /**
     * Delete a share link. Anyone holding the URL gets a "not found" page from now on.
     */
    public function destroy(ShareLink $shareLink): RedirectResponse
    {
        $shareLink->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Share link deleted. The old URL no longer works.']);

        return to_route('share-links.index');
    }
}
