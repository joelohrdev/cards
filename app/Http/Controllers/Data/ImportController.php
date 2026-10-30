<?php

namespace App\Http\Controllers\Data;

use App\Enums\ImportStatus;
use App\Enums\Sport;
use App\Http\Controllers\Controller;
use App\Http\Requests\Data\StoreImportRequest;
use App\Http\Requests\Data\UpdateImportMappingRequest;
use App\Jobs\ProcessImport;
use App\Models\CollectionItem;
use App\Models\Import;
use App\Models\ItemImage;
use App\Models\Tag;
use App\Support\FormOptions;
use App\Support\Import\CsvReader;
use App\Support\Import\ImportFields;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ImportController extends Controller
{
    /**
     * Upload form plus the history of past imports.
     */
    public function index(Request $request): Response
    {
        return Inertia::render('imports/index', [
            'imports' => Import::query()
                ->with('tag')
                ->latest()
                ->limit(50)
                ->get()
                ->map(fn (Import $import): array => $this->summary($import)),
            'maxMegabytes' => StoreImportRequest::MAX_KILOBYTES / 1024,
        ]);
    }

    /**
     * Store the uploaded file privately and read its header row.
     */
    public function store(StoreImportRequest $request): RedirectResponse
    {
        $file = $request->file('file');
        $path = $file->store('imports', Import::DISK);

        if ($path === false) {
            throw ValidationException::withMessages(['file' => 'The file couldn’t be saved. Try uploading it again.']);
        }

        $reader = new CsvReader(Storage::disk(Import::DISK)->path($path));
        $headers = $reader->headers();
        $totalRows = $reader->countRows();

        if (count(array_filter($headers)) === 0 || $totalRows === 0) {
            Storage::disk(Import::DISK)->delete($path);

            throw ValidationException::withMessages([
                'file' => 'That file has no rows to import. The first row should be column headings, with one card per row below it.',
            ]);
        }

        $import = $request->user()->imports()->create([
            'original_filename' => $file->getClientOriginalName(),
            'path' => $path,
            'headers' => array_map(fn (string $header, int $index): string => $header === '' ? 'Column '.($index + 1) : $header, $headers, array_keys($headers)),
            'status' => ImportStatus::Pending,
            'total_rows' => $totalRows,
        ]);

        return to_route('imports.mapping', $import);
    }

    /**
     * Match spreadsheet columns to card fields.
     */
    public function mapping(Request $request, Import $import): Response|RedirectResponse
    {
        if ($import->status !== ImportStatus::Pending) {
            return to_route('imports.show', $import);
        }

        $reader = new CsvReader(Storage::disk(Import::DISK)->path($import->path));

        return Inertia::render('imports/mapping', [
            'import' => $this->summary($import),
            'headers' => $import->headers,
            'preview' => $reader->preview(5),
            'fields' => ImportFields::forDisplay(),
            'mapping' => $import->mapping ?? ImportFields::guessMapping($import->headers),
            'defaults' => [
                'sport' => $import->defaults['sport'] ?? null,
                'tags' => $import->defaults['tags'] ?? [],
                'storage_location_id' => $import->defaults['storage_location_id'] ?? null,
            ],
            'importTagName' => $this->importTagName(),
            'sports' => Sport::options(),
            'locations' => FormOptions::locations(),
        ]);
    }

    /**
     * Save the mapping and start importing in the background.
     */
    public function update(UpdateImportMappingRequest $request, Import $import): RedirectResponse
    {
        if ($import->status !== ImportStatus::Pending) {
            return to_route('imports.show', $import);
        }

        $settings = $request->importSettings();
        $tag = $request->user()->tags()->create([
            'name' => $this->importTagName(),
            'color' => 'slate',
        ]);

        $import->fill([...$settings, 'status' => ImportStatus::Processing]);
        $import->tag()->associate($tag);
        $import->save();

        ProcessImport::dispatch($import);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Import started. You can leave this page; it keeps running.']);

        return to_route('imports.show', $import);
    }

    /**
     * Progress and results.
     */
    public function show(Import $import): Response
    {
        $import->load('tag');

        return Inertia::render('imports/show', [
            'import' => [
                ...$this->summary($import),
                'failures' => $import->failures ?? [],
                'failures_truncated' => $import->failedRows() > count($import->failures ?? []),
                'tag_items_count' => $import->tag?->collectionItems()->count() ?? 0,
            ],
        ]);
    }

    /**
     * Delete the import record and its uploaded file. Imported cards are kept.
     */
    public function destroy(Import $import): RedirectResponse
    {
        if ($import->isRunning()) {
            return back()->withErrors(['import' => 'Wait for the import to finish before removing it.']);
        }

        Storage::disk(Import::DISK)->delete($import->path);
        $import->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Import removed from the history. Its cards are still in your collection.']);

        return to_route('imports.index');
    }

    /**
     * Undo an import: delete every card carrying its tag, then the tag itself.
     */
    public function destroyCards(Request $request, Import $import): RedirectResponse
    {
        if ($import->isRunning()) {
            return back()->withErrors(['import' => 'Wait for the import to finish before deleting its cards.']);
        }

        $tag = $import->tag;

        if ($tag === null) {
            return back()->withErrors(['import' => 'This import’s tag was deleted, so its cards can no longer be found automatically.']);
        }

        $deleted = 0;

        CollectionItem::query()
            ->whereHas('tags', fn (Builder $query) => $query->whereKey($tag->id))
            ->with('images')
            ->lazyById()
            ->each(function (CollectionItem $item) use (&$deleted): void {
                $paths = $item->images->pluck('path')->all();
                $item->delete();
                Storage::disk(ItemImage::DISK)->delete($paths);
                $deleted++;
            });

        $tag->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => "Deleted {$deleted} ".($deleted === 1 ? 'card' : 'cards').' from this import.']);

        return to_route('imports.show', $import);
    }

    /**
     * @return array<string, mixed>
     */
    private function summary(Import $import): array
    {
        return [
            'id' => $import->id,
            'original_filename' => $import->original_filename,
            'status' => $import->status->value,
            'total_rows' => $import->total_rows,
            'processed_rows' => $import->processed_rows,
            'imported_rows' => $import->imported_rows,
            'failed_rows' => $import->failedRows(),
            'tag' => $import->tag ? ['id' => $import->tag->id, 'name' => $import->tag->name, 'color' => $import->tag->color] : null,
            'created_at' => $import->created_at->toIso8601String(),
            'completed_at' => $import->completed_at?->toIso8601String(),
        ];
    }

    /**
     * "Import 2026-10-29", or "Import 2026-10-29 (2)" when that tag already exists.
     */
    private function importTagName(): string
    {
        $base = 'Import '.now()->toDateString();
        $existing = Tag::query()->where('name', 'like', $base.'%')->pluck('name');
        $name = $base;
        $suffix = 2;

        while ($existing->contains($name)) {
            $name = "{$base} ({$suffix})";
            $suffix++;
        }

        return $name;
    }
}
