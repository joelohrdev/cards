<?php

namespace App\Http\Controllers\Organize;

use App\Enums\LocationType;
use App\Http\Controllers\Controller;
use App\Http\Resources\CollectionItemResource;
use App\Models\CollectionItem;
use App\Models\StorageLocation;
use App\Support\FormOptions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class StorageLocationController extends Controller
{
    /**
     * Show the storage tree with counts.
     */
    public function index(Request $request): Response
    {
        $counts = CollectionItem::query()
            ->inHand()
            ->whereNotNull('storage_location_id')
            ->groupBy('storage_location_id')
            ->selectRaw('storage_location_id, count(*) as aggregate')
            ->pluck('aggregate', 'storage_location_id');

        $unplaced = CollectionItem::query()->inHand()->whereNull('storage_location_id')->count();

        return Inertia::render('organize/locations/index', [
            'locations' => collect(FormOptions::locations())->map(fn (array $location): array => [
                ...$location,
                'items_count' => (int) ($counts[$location['id']] ?? 0),
            ]),
            'unplacedCount' => $unplaced,
            'types' => LocationType::options(),
        ]);
    }

    /**
     * Create a location.
     */
    public function store(Request $request): RedirectResponse
    {
        $request->user()->storageLocations()->create($this->validated($request));

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Location added.']);

        return back();
    }

    /**
     * Show the cards stored in one location (also a printable inventory sheet).
     */
    public function show(Request $request, StorageLocation $location): Response
    {
        $items = $location->collectionItems()
            ->inHand()
            ->with(CollectionItemResource::LIST_RELATIONS)
            ->orderByRaw('storage_position IS NULL')
            ->orderBy('storage_position')
            ->orderBy('id')
            ->get()
            ->map(fn (CollectionItem $item): array => CollectionItemResource::make($item)->resolve());

        return Inertia::render('organize/locations/show', [
            'location' => [
                'id' => $location->id,
                'name' => $location->name,
                'path' => $location->path(),
                'type' => $location->type->value,
                'code' => $location->code,
                'notes' => $location->notes,
                'parent_id' => $location->parent_id,
            ],
            'children' => $location->children()->orderBy('name')->get(['id', 'name', 'type']),
            'items' => $items,
            'types' => LocationType::options(),
            'locations' => FormOptions::locations(),
        ]);
    }

    /**
     * Update a location.
     */
    public function update(Request $request, StorageLocation $location): RedirectResponse
    {
        $data = $this->validated($request);

        if (($data['parent_id'] ?? null) !== null && $this->wouldCreateCycle($location, (int) $data['parent_id'])) {
            return back()->withErrors(['parent_id' => 'A location can’t be placed inside itself.']);
        }

        $location->update($data);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Location updated.']);

        return back();
    }

    /**
     * Delete a location. Its cards become unplaced and child locations are removed.
     */
    public function destroy(StorageLocation $location): RedirectResponse
    {
        $location->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Location deleted.']);

        return to_route('locations.index');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'type' => ['required', Rule::enum(LocationType::class)],
            'parent_id' => ['nullable', 'integer', Rule::exists('storage_locations', 'id')],
            'code' => ['nullable', 'string', 'max:30'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
    }

    private function wouldCreateCycle(StorageLocation $location, int $parentId): bool
    {
        $current = StorageLocation::query()->find($parentId);

        for ($depth = 0; $current !== null && $depth < 20; $depth++) {
            if ($current->id === $location->id) {
                return true;
            }

            $current = $current->parent;
        }

        return false;
    }
}
