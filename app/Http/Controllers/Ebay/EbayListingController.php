<?php

namespace App\Http\Controllers\Ebay;

use App\Enums\ItemStatus;
use App\Enums\ListingStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Ebay\EbayListingRequest;
use App\Http\Resources\CollectionItemResource;
use App\Models\CollectionItem;
use App\Models\EbayListing;
use App\Services\Ebay\EbayClient;
use App\Services\Ebay\EbayException;
use App\Services\Ebay\ListingContent;
use App\Services\Ebay\ListingPublisher;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class EbayListingController extends Controller
{
    /**
     * List the user's eBay listings, filtered by status.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();
        $validated = $request->validate(['status' => ['nullable', Rule::enum(ListingStatus::class)]]);
        $status = $validated['status'] ?? null;

        $counts = EbayListing::query()
            ->toBase()
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $listings = EbayListing::query()
            ->when($status, fn ($query) => $query->where('status', $status))
            ->with(collect(CollectionItemResource::LIST_RELATIONS)->map(fn (string $relation): string => 'collectionItem.'.$relation)->all())
            ->latest('updated_at')
            ->latest('id')
            ->paginate(30)
            ->withQueryString()
            ->through(fn (EbayListing $listing): array => [
                ...$this->listingData($listing),
                'item' => CollectionItemResource::make($listing->collectionItem)->resolve(),
            ]);

        $account = $user->ebayAccount;

        return Inertia::render('ebay/listings', [
            'listings' => $listings,
            'status' => $status,
            'counts' => collect(ListingStatus::cases())->mapWithKeys(fn (ListingStatus $case): array => [$case->value => (int) ($counts[$case->value] ?? 0)]),
            'connected' => $account !== null,
            'ready' => $account?->isReadyToList() ?? false,
        ]);
    }

    /**
     * Show the listing form for a card, prefilled from the card or from its existing listing.
     */
    public function edit(Request $request, CollectionItem $item): Response
    {
        $item->load([...CollectionItemResource::LIST_RELATIONS, 'images']);
        $listing = $this->currentListing($item);
        $content = ListingContent::for($item);
        $account = $request->user()->ebayAccount;
        $usesListingValues = $listing !== null && in_array($listing->status, [ListingStatus::Draft, ListingStatus::Active], true);

        return Inertia::render('ebay/listing', [
            'item' => CollectionItemResource::make($item)->resolve(),
            'listing' => $listing ? $this->listingData($listing) : null,
            'defaults' => [
                'title' => $usesListingValues ? $listing->title : $content->title(),
                'description' => $usesListingValues ? $listing->description : $content->description(),
                'price' => Money::toDollars($usesListingValues ? $listing->price : ($listing->price ?? $item->estimated_value)) ?? '',
            ],
            'suggestedTitle' => $content->title(),
            'aspects' => $content->aspects(),
            'photos' => $content->imageUrls(),
            'warnings' => $content->warnings(),
            'canList' => in_array($item->status, ItemStatus::inHand(), true),
            'connected' => $account !== null,
            'ready' => $account?->isReadyToList() ?? false,
            'configured' => EbayClient::isConfigured(),
            'categoryId' => ListingContent::CATEGORY_ID,
        ]);
    }

    /**
     * Save a draft listing for a card (or relist an ended one), publishing it when asked.
     */
    public function store(EbayListingRequest $request, CollectionItem $item, ListingPublisher $publisher): RedirectResponse
    {
        if (! in_array($item->status, ItemStatus::inHand(), true)) {
            throw ValidationException::withMessages(['title' => "This card is marked {$item->status->label()}, so it can’t be listed."]);
        }

        $listing = $this->currentListing($item);

        if ($listing?->status === ListingStatus::Active || $listing?->status === ListingStatus::Sold) {
            throw ValidationException::withMessages(['title' => 'This card already has an eBay listing. Edit that listing instead.']);
        }

        $listing ??= $request->user()->ebayListings()->make([
            'collection_item_id' => $item->id,
            'sku' => 'CARD-'.$item->id,
        ]);

        $listing->fill([
            ...$this->listingAttributes($request),
            'status' => ListingStatus::Draft,
            'listing_id' => null,
            'error' => null,
        ])->save();

        return $this->afterSave($request, $listing, $publisher);
    }

    /**
     * Edit a draft, or revise the title, description and price of a live listing.
     */
    public function update(EbayListingRequest $request, EbayListing $listing, ListingPublisher $publisher): RedirectResponse
    {
        $listing->fill($this->listingAttributes($request));

        if ($listing->status === ListingStatus::Active) {
            try {
                $publisher->revise($listing);
            } catch (EbayException $exception) {
                throw ValidationException::withMessages(['ebay' => 'eBay didn’t accept the changes: '.$exception->getMessage()]);
            }

            Inertia::flash('toast', ['type' => 'success', 'message' => 'Listing updated on eBay.']);

            return to_route('ebay.items.listing', $listing->collection_item_id);
        }

        if ($listing->status !== ListingStatus::Draft) {
            throw ValidationException::withMessages(['title' => 'Only drafts and live listings can be edited.']);
        }

        $listing->save();

        return $this->afterSave($request, $listing, $publisher);
    }

    /**
     * Publish a saved draft.
     */
    public function publish(EbayListing $listing, ListingPublisher $publisher): RedirectResponse
    {
        if ($listing->status !== ListingStatus::Draft) {
            throw ValidationException::withMessages(['ebay' => 'Only drafts can be published.']);
        }

        $this->flashPublishResult($publisher->publish($listing));

        return back();
    }

    /**
     * End a live listing on eBay and put the card back in the collection.
     */
    public function end(EbayListing $listing, ListingPublisher $publisher): RedirectResponse
    {
        if ($listing->status !== ListingStatus::Active) {
            throw ValidationException::withMessages(['ebay' => 'Only live listings can be ended.']);
        }

        try {
            $publisher->end($listing);
        } catch (EbayException $exception) {
            throw ValidationException::withMessages(['ebay' => 'eBay couldn’t end the listing: '.$exception->getMessage()]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Listing ended. The card is back in your collection.']);

        return back();
    }

    /**
     * Delete a draft that was never published.
     */
    public function destroy(EbayListing $listing): RedirectResponse
    {
        if ($listing->status !== ListingStatus::Draft || $listing->published_at !== null) {
            throw ValidationException::withMessages(['ebay' => 'Only drafts that were never published can be deleted.']);
        }

        $listing->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Draft deleted.']);

        return to_route('ebay.listings.index');
    }

    private function afterSave(EbayListingRequest $request, EbayListing $listing, ListingPublisher $publisher): RedirectResponse
    {
        if ($request->boolean('publish')) {
            $this->flashPublishResult($publisher->publish($listing));
        } else {
            Inertia::flash('toast', ['type' => 'success', 'message' => 'Draft saved.']);
        }

        return to_route('ebay.items.listing', $listing->collection_item_id);
    }

    private function flashPublishResult(bool $published): void
    {
        Inertia::flash('toast', $published
            ? ['type' => 'success', 'message' => 'Listed on eBay.']
            : ['type' => 'error', 'message' => 'eBay didn’t accept the listing. It’s saved as a draft — see the error on the page.']);
    }

    /**
     * The card's listing record. Each card has one SKU, so relisting reuses the same record.
     */
    private function currentListing(CollectionItem $item): ?EbayListing
    {
        return $item->ebayListings()->latest('id')->first();
    }

    /**
     * @return array{title: string, description: string, price: int}
     */
    private function listingAttributes(EbayListingRequest $request): array
    {
        return [
            'title' => $request->validated('title'),
            'description' => $request->validated('description'),
            'price' => (int) Money::toCents($request->validated('price')),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function listingData(EbayListing $listing): array
    {
        return [
            'id' => $listing->id,
            'collection_item_id' => $listing->collection_item_id,
            'sku' => $listing->sku,
            'title' => $listing->title,
            'price' => $listing->price,
            'status' => $listing->status->value,
            'error' => $listing->error,
            'url' => $listing->url(),
            'published_at' => $listing->published_at?->toIso8601String(),
            'ended_at' => $listing->ended_at?->toIso8601String(),
            'updated_at' => $listing->updated_at?->toIso8601String(),
        ];
    }
}
