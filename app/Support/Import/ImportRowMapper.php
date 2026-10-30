<?php

namespace App\Support\Import;

use App\Actions\Catalog\ResolveCardSet;
use App\Actions\Collection\SaveCollectionItem;
use App\Enums\Grader;
use App\Enums\LocationType;
use App\Enums\RawCondition;
use App\Enums\Sport;
use App\Models\Import;
use App\Models\StorageLocation;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/**
 * Turns one spreadsheet row into the input array {@see SaveCollectionItem}
 * expects (the same shape as the add-a-card form), applying the import's column mapping and defaults.
 */
class ImportRowMapper
{
    /**
     * Storage location ids already found or created during this run, keyed by lower-cased path.
     *
     * @var array<string, int>
     */
    private array $locationIds = [];

    public function __construct(
        private Import $import,
        private ResolveCardSet $resolveCardSet,
    ) {}

    /**
     * @param  list<string>  $row
     * @return array{data: array<string, mixed>, quantity: int}
     *
     * @throws InvalidImportRow
     */
    public function map(array $row): array
    {
        $cell = fn (string $field): ?string => $this->cell($row, $field);
        $defaults = $this->import->defaults ?? [];

        [$year, $season] = ValueParser::year($cell('year')) ?? throw new InvalidImportRow('Missing the year.');
        $sport = ValueParser::sport($cell('sport')) ?? Sport::tryFrom((string) ($defaults['sport'] ?? ''))
            ?? throw new InvalidImportRow('Missing the sport. Map a sport column or choose a default sport.');
        $setName = ValueParser::text($cell('set')) ?? throw new InvalidImportRow('Missing the set name.');
        $number = ltrim((string) ValueParser::text($cell('number')), '# ');

        if ($number === '') {
            throw new InvalidImportRow('Missing the card number.');
        }

        [$serialNumber, $printRunFromSerial] = ValueParser::serial($cell('serial_number')) ?? [null, null];
        $printRun = ValueParser::printRun($cell('print_run')) ?? $printRunFromSerial;
        $parallel = ValueParser::text($cell('parallel'));
        [$grader, $grade, $condition] = $this->condition($cell('grader'), $cell('grade'), $cell('raw_condition'));
        $isGraded = $grade !== null;

        $data = [
            'card' => [
                'number' => $number,
                'name' => ValueParser::text($cell('name')),
                'players' => ValueParser::list($cell('players'), '/\s*[\/;&]\s*/') ?: null,
                'team' => ValueParser::text($cell('team')),
                'subset' => ValueParser::text($cell('subset')),
                'is_rookie' => ValueParser::boolean($cell('is_rookie')),
                'is_autograph' => ValueParser::boolean($cell('is_autograph')),
                'is_memorabilia' => ValueParser::boolean($cell('is_memorabilia')),
                'is_short_print' => ValueParser::boolean($cell('is_short_print')),
                'print_run' => $parallel === null ? $printRun : null,
            ],
            'parallel_name' => $parallel,
            'parallel_print_run' => $parallel === null ? null : $printRun,
            'serial_number' => $serialNumber,
            'is_graded' => $isGraded,
            'grader' => $isGraded ? $grader?->value : null,
            'grade' => $grade,
            'cert_number' => $isGraded ? ValueParser::text($cell('cert_number')) : null,
            'raw_condition' => $isGraded ? null : $condition?->value,
            'status' => ValueParser::status($cell('status'))?->value,
            'purchase_price' => ValueParser::money($cell('purchase_price'), 'price paid'),
            'purchase_date' => ValueParser::date($cell('purchase_date')),
            'purchase_source' => ValueParser::text($cell('purchase_source')),
            'estimated_value' => ValueParser::money($cell('estimated_value'), 'value'),
            'storage_position' => ValueParser::text($cell('storage_position')),
            'notes' => ValueParser::text($cell('notes')),
            'tags' => array_values(array_unique([
                ...ValueParser::list($cell('tags')),
                ...($defaults['tags'] ?? []),
                ...($this->import->tag ? [$this->import->tag->name] : []),
            ])),
        ];

        $quantity = ValueParser::quantity($cell('quantity'));
        $this->validate($data, $setName, ValueParser::text($cell('manufacturer')));

        $data['card_set_id'] = $this->resolveCardSet->handle($sport, $year, ValueParser::text($cell('manufacturer')), $setName, $season)->id;
        $data['storage_location_id'] = $this->locationId(ValueParser::text($cell('location'))) ?? $this->defaultLocationId();

        return ['data' => $data, 'quantity' => $quantity];
    }

    /**
     * Work out grading from up to three columns. A grade with a company ("PSA 10") can sit in
     * the grade column or even the condition column; a bare grade needs the company column.
     *
     * @return array{0: Grader|null, 1: string|null, 2: RawCondition|null}
     */
    private function condition(?string $graderCell, ?string $gradeCell, ?string $conditionCell): array
    {
        $grader = ValueParser::grader($graderCell);
        [$graderFromGrade, $grade] = ValueParser::grade($gradeCell) ?? [null, null];
        $condition = ValueParser::condition($conditionCell);

        if ($grade === null && $condition === null && ! ValueParser::blank($conditionCell)) {
            [$graderFromCondition, $gradeFromCondition] = ValueParser::grade($conditionCell) ?? [null, null];

            if ($graderFromCondition !== null) {
                $graderFromGrade = $graderFromCondition;
                $grade = $gradeFromCondition;
            }
        }

        $grader ??= $graderFromGrade;

        if ($grade !== null && $grader === null) {
            throw new InvalidImportRow("The grade “{$grade}” has no grading company. Map a grading company column, or write grades like “PSA 10”.");
        }

        if ($grade === null && $grader !== null) {
            throw new InvalidImportRow("Graded by {$grader->value} but the grade is missing.");
        }

        return [$grader, $grade, $condition];
    }

    /**
     * Enforce the same limits as the add-a-card form so the database never sees oversized values.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws InvalidImportRow
     */
    private function validate(array $data, string $setName, ?string $manufacturer): void
    {
        $validator = Validator::make([...$data, 'set_name' => $setName, 'manufacturer' => $manufacturer], [
            'set_name' => ['string', 'max:150'],
            'manufacturer' => ['nullable', 'string', 'max:100'],
            'card.number' => ['string', 'max:30'],
            'card.name' => ['nullable', 'string', 'max:200'],
            'card.players' => ['nullable', 'array', 'max:10'],
            'card.players.*' => ['string', 'max:100'],
            'card.team' => ['nullable', 'string', 'max:100'],
            'card.subset' => ['nullable', 'string', 'max:100'],
            'card.print_run' => ['nullable', 'integer', 'min:1'],
            'parallel_name' => ['nullable', 'string', 'max:100'],
            'parallel_print_run' => ['nullable', 'integer', 'min:1'],
            'serial_number' => ['nullable', 'integer', 'min:1'],
            'grade' => ['nullable', 'string', 'max:20'],
            'cert_number' => ['nullable', 'string', 'max:50'],
            'purchase_price' => ['nullable', 'numeric', 'min:0', 'max:10000000'],
            'purchase_source' => ['nullable', 'string', 'max:150'],
            'estimated_value' => ['nullable', 'numeric', 'min:0', 'max:10000000'],
            'storage_position' => ['nullable', 'string', 'max:50'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'tags' => ['array', 'max:30'],
            'tags.*' => ['string', 'max:50'],
        ], [], [
            'set_name' => 'set name',
            'manufacturer' => 'brand',
            'card.number' => 'card number',
            'card.name' => 'player / card name',
            'card.players' => 'players',
            'card.players.*' => 'player name',
            'card.team' => 'team',
            'card.subset' => 'subset',
            'card.print_run' => 'print run',
            'parallel_name' => 'parallel',
            'parallel_print_run' => 'print run',
            'serial_number' => 'serial number',
            'cert_number' => 'cert number',
            'purchase_price' => 'price paid',
            'purchase_source' => 'purchased from',
            'estimated_value' => 'value',
            'storage_position' => 'position',
            'tags.*' => 'tag',
        ]);

        if ($validator->fails()) {
            throw new InvalidImportRow((string) $validator->errors()->first());
        }
    }

    /**
     * Find or create a nested storage location from a path like "Closet / Box 4 / Row 2".
     */
    private function locationId(?string $path): ?int
    {
        $names = ValueParser::list($path, '/\s*[\/>\\\\]\s*/');

        if ($names === []) {
            return null;
        }

        $key = Str::lower(implode(' / ', $names));

        if (isset($this->locationIds[$key])) {
            return $this->locationIds[$key];
        }

        $user = $this->import->user;
        $parentId = null;

        foreach (array_slice($names, 0, 10) as $name) {
            $name = Str::limit($name, 100, '');

            $location = StorageLocation::query()
                ->where('parent_id', $parentId)
                ->whereRaw('lower(name) = ?', [Str::lower($name)])
                ->first()
                ?? $user->storageLocations()->create([
                    'parent_id' => $parentId,
                    'name' => $name,
                    'type' => self::locationType($name),
                ]);

            $parentId = $location->id;
        }

        return $this->locationIds[$key] = $parentId;
    }

    private function defaultLocationId(): ?int
    {
        $id = $this->import->defaults['storage_location_id'] ?? null;

        if ($id === null) {
            return null;
        }

        return StorageLocation::query()->whereKey($id)->value('id');
    }

    /**
     * Guess the location type from its name, e.g. "Box 4" → box, "Red binder" → binder.
     */
    private static function locationType(string $name): LocationType
    {
        foreach (LocationType::cases() as $type) {
            if ($type !== LocationType::Other && preg_match('/\b'.$type->value.'s?\b/i', $name)) {
                return $type;
            }
        }

        return LocationType::Other;
    }

    /**
     * @param  list<string>  $row
     */
    private function cell(array $row, string $field): ?string
    {
        $index = $this->import->mapping[$field] ?? null;

        return $index === null ? null : ($row[(int) $index] ?? null);
    }
}
