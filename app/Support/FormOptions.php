<?php

namespace App\Support;

use App\Enums\Grader;
use App\Enums\ItemStatus;
use App\Enums\LocationType;
use App\Enums\RawCondition;
use App\Enums\SalePlatform;
use App\Enums\Sport;
use App\Models\StorageLocation;
use App\Models\Tag;

/**
 * Select-box options shared by the collection forms and filters.
 */
class FormOptions
{
    /**
     * @return array<string, mixed>
     */
    public static function for(): array
    {
        return [
            'sports' => Sport::options(),
            'graders' => Grader::options(),
            'conditions' => RawCondition::options(),
            'statuses' => ItemStatus::options(),
            'platforms' => SalePlatform::options(),
            'locationTypes' => LocationType::options(),
            'tags' => Tag::query()->orderBy('name')->get(['id', 'name', 'color']),
            'locations' => self::locations(),
        ];
    }

    /**
     * Every storage location, flattened into breadcrumb paths, ordered as a tree.
     *
     * @return list<array{id: int, name: string, path: string, depth: int, parent_id: int|null, type: string}>
     */
    public static function locations(): array
    {
        $locations = StorageLocation::query()->orderBy('name')->get();
        $byParent = $locations->groupBy(fn (StorageLocation $location): int => $location->parent_id ?? 0);
        $flattened = [];

        $walk = function (int $parentId, string $prefix, int $depth) use (&$walk, &$flattened, $byParent): void {
            foreach ($byParent->get($parentId, collect()) as $location) {
                $path = $prefix === '' ? $location->name : $prefix.' / '.$location->name;
                $flattened[] = [
                    'id' => $location->id,
                    'name' => $location->name,
                    'path' => $path,
                    'depth' => $depth,
                    'parent_id' => $location->parent_id,
                    'type' => $location->type->value,
                ];

                if ($depth < 10) {
                    $walk($location->id, $path, $depth + 1);
                }
            }
        };

        $walk(0, '', 0);

        return $flattened;
    }
}
