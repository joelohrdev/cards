<?php

namespace App\Http\Controllers\Data;

use App\Enums\ItemStatus;
use App\Http\Controllers\Controller;
use App\Models\CollectionItem;
use App\Support\Export\CollectionCsv;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportController extends Controller
{
    /**
     * Explain the available exports.
     */
    public function index(Request $request): Response
    {
        $items = CollectionItem::query();

        return Inertia::render('exports/index', [
            'counts' => [
                'all' => (clone $items)->count(),
                'in_hand' => (clone $items)->inHand()->count(),
            ],
        ]);
    }

    /**
     * Download every card in the collection as a CSV the importer can read back.
     */
    public function collection(Request $request): StreamedResponse
    {
        $csv = new CollectionCsv;

        return response()->streamDownload(function () use ($csv): void {
            $stream = fopen('php://output', 'w');

            if ($stream === false) {
                return;
            }

            $csv->write($stream);
            fclose($stream);
        }, $csv->filename(), ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * A printable inventory for insurance: every card, most valuable first.
     */
    public function insurance(Request $request): Response
    {
        $validated = $request->validate([
            'status' => ['nullable', Rule::in(['in-hand', 'all', ...array_column(ItemStatus::cases(), 'value')])],
        ]);
        $status = $validated['status'] ?? 'in-hand';

        $items = CollectionItem::query()
            ->when($status === 'in-hand', fn ($query) => $query->inHand())
            ->when(! in_array($status, ['in-hand', 'all'], true), fn ($query) => $query->where('status', $status))
            ->with(['card.cardSet.manufacturer', 'parallel', 'frontImage', 'storageLocation.parent.parent'])
            ->orderByRaw('estimated_value IS NULL')
            ->orderByDesc('estimated_value')
            ->orderBy('id')
            ->get();

        return Inertia::render('exports/insurance', [
            'status' => $status,
            'statuses' => [
                ['value' => 'in-hand', 'label' => 'In hand (owned, listed, at grading)'],
                ['value' => 'all', 'label' => 'Every status'],
                ...ItemStatus::options(),
            ],
            'generatedAt' => now()->toIso8601String(),
            'owner' => $request->user()->name,
            'totals' => [
                'count' => $items->count(),
                'value' => (int) $items->sum('estimated_value'),
                'cost' => (int) $items->sum('purchase_price'),
                'unvalued' => $items->whereNull('estimated_value')->count(),
            ],
            'items' => $items->map(fn (CollectionItem $item): array => [
                'id' => $item->id,
                'title' => $item->title(),
                'image' => $item->frontImage?->url,
                'card' => [
                    'number' => $item->card->number,
                    'name' => $item->card->name,
                    'set' => ['full_name' => $item->card->cardSet->full_name, 'year' => $item->card->cardSet->year, 'name' => $item->card->cardSet->name, 'manufacturer' => $item->card->cardSet->manufacturer?->name],
                ],
                'parallel' => $item->parallel ? ['id' => $item->parallel->id, 'name' => $item->parallel->name, 'print_run' => $item->parallel->print_run] : null,
                'condition_label' => $item->conditionLabel(),
                'cert_number' => $item->cert_number,
                'status_label' => $item->status->label(),
                'location' => $item->storageLocation?->path(),
                'storage_position' => $item->storage_position,
                'purchase_price' => $item->purchase_price,
                'purchase_date' => $item->purchase_date?->toDateString(),
                'estimated_value' => $item->estimated_value,
            ])->values(),
        ]);
    }
}
