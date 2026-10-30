<?php

namespace App\Actions\Collection;

use App\Actions\Catalog\ResolveCard;
use App\Actions\Catalog\ResolveCardSet;
use App\Enums\ItemStatus;
use App\Enums\Sport;
use App\Models\Card;
use App\Models\CardSet;
use App\Models\CollectionItem;
use App\Models\Parallel;
use App\Models\Tag;
use App\Models\User;
use App\Models\WantListEntry;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Create or update a collection item from validated form input, resolving the
 * catalog card, parallel and tags along the way.
 */
class SaveCollectionItem
{
    public function __construct(
        private ResolveCardSet $resolveCardSet,
        private ResolveCard $resolveCard,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(User $user, array $data, ?CollectionItem $item = null): CollectionItem
    {
        return DB::transaction(function () use ($user, $data, $item): CollectionItem {
            $card = $this->resolveCard($data);
            $parallel = $this->resolveParallel($card, $data);

            $item ??= new CollectionItem(['status' => ItemStatus::Owned]);
            $item->user()->associate($user);
            $item->card()->associate($card);
            $item->parallel()->associate($parallel);

            $isGraded = (bool) ($data['is_graded'] ?? false);
            $estimatedValue = array_key_exists('estimated_value', $data) ? Money::toCents($data['estimated_value']) : $item->estimated_value;

            $item->fill([
                'serial_number' => $data['serial_number'] ?? null,
                'is_graded' => $isGraded,
                'grader' => $isGraded ? ($data['grader'] ?? null) : null,
                'grade' => $isGraded ? ($data['grade'] ?? null) : null,
                'cert_number' => $isGraded ? ($data['cert_number'] ?? null) : null,
                'raw_condition' => $isGraded ? null : ($data['raw_condition'] ?? null),
                'purchase_price' => Money::toCents($data['purchase_price'] ?? null),
                'purchase_date' => $data['purchase_date'] ?? null,
                'purchase_source' => $data['purchase_source'] ?? null,
                'storage_location_id' => $data['storage_location_id'] ?? null,
                'storage_position' => $data['storage_position'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]);

            if (filled($data['status'] ?? null)) {
                $item->status = ItemStatus::from($data['status']);
            }

            if ($estimatedValue !== $item->estimated_value || ! $item->exists) {
                $item->estimated_value = $estimatedValue;
                $item->value_updated_at = $estimatedValue === null ? null : now();
            }

            $isNew = ! $item->exists;
            $item->save();

            if ($isNew) {
                WantListEntry::query()
                    ->where('card_id', $item->card_id)
                    ->where(fn ($entries) => $entries->whereNull('parallel_id')->orWhere('parallel_id', $item->parallel_id))
                    ->delete();
            }

            if (array_key_exists('tags', $data)) {
                $item->tags()->sync($this->resolveTagIds($user, $data['tags'] ?? []));
            }

            return $item;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function resolveCard(array $data): Card
    {
        if (filled($data['card_id'] ?? null)) {
            return Card::query()->findOrFail((int) $data['card_id']);
        }

        $cardSet = match (true) {
            filled($data['card_set_id'] ?? null) => CardSet::query()->findOrFail((int) $data['card_set_id']),
            filled($data['new_set'] ?? null) => $this->resolveCardSet->handle(
                Sport::from($data['new_set']['sport']),
                (int) $data['new_set']['year'],
                $data['new_set']['manufacturer'] ?? null,
                $data['new_set']['name'],
            ),
            default => throw ValidationException::withMessages(['card_set_id' => 'Choose a set for this card.']),
        };

        return $this->resolveCard->handle($cardSet, $data['card']);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function resolveParallel(Card $card, array $data): ?Parallel
    {
        if (filled($data['parallel_id'] ?? null)) {
            $parallel = Parallel::query()->findOrFail((int) $data['parallel_id']);

            if ($parallel->card_set_id !== $card->card_set_id) {
                throw ValidationException::withMessages(['parallel_id' => 'That parallel belongs to a different set.']);
            }

            return $parallel;
        }

        if (filled($data['parallel_name'] ?? null)) {
            return Parallel::query()->firstOrCreate(
                ['card_set_id' => $card->card_set_id, 'name' => Str::squish($data['parallel_name'])],
                ['print_run' => $data['parallel_print_run'] ?? null],
            );
        }

        return null;
    }

    /**
     * @param  list<string>  $names
     * @return list<int>
     */
    public function resolveTagIds(User $user, array $names): array
    {
        return array_values(collect($names)
            ->map(fn (string $name): string => Str::squish($name))
            ->filter()
            ->unique(fn (string $name): string => Str::lower($name))
            ->map(fn (string $name): int => Tag::query()->firstOrCreate(
                ['name' => $name],
                ['user_id' => $user->id, 'color' => Tag::COLORS[crc32(Str::lower($name)) % count(Tag::COLORS)]],
            )->id)
            ->all());
    }
}
