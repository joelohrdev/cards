<?php

namespace App\Support\Import;

/**
 * The app fields a spreadsheet column can be mapped to, and the header names that
 * are recognised for each one when guessing the mapping.
 *
 * Header names are compared after lower-casing and removing spaces and punctuation
 * (except "#" and "/"), so "Card #", "card#" and "CARD  #" all match the same alias.
 */
final class ImportFields
{
    /**
     * Ordered as they appear on the mapping screen. Earlier fields win a contested header.
     *
     * @var array<string, array{label: string, group: string, required?: bool, hint?: string, aliases: list<string>}>
     */
    public const array FIELDS = [
        'year' => [
            'label' => 'Year',
            'group' => 'Card',
            'required' => true,
            'hint' => '1989, or a season like 2023-24.',
            'aliases' => ['year', 'yr', 'season', 'yearseason', 'releaseyear', 'cardyear'],
        ],
        'sport' => [
            'label' => 'Sport',
            'group' => 'Card',
            'hint' => 'Leave unmapped to use the default sport below.',
            'aliases' => ['sport', 'league', 'category'],
        ],
        'manufacturer' => [
            'label' => 'Brand',
            'group' => 'Card',
            'hint' => 'Topps, Panini, Upper Deck…',
            'aliases' => ['brand', 'manufacturer', 'mfr', 'mfg', 'maker', 'make'],
        ],
        'set' => [
            'label' => 'Set',
            'group' => 'Card',
            'required' => true,
            'aliases' => ['set', 'setname', 'product', 'series', 'cardset'],
        ],
        'number' => [
            'label' => 'Card number',
            'group' => 'Card',
            'required' => true,
            'aliases' => ['#', 'card#', 'cardnumber', 'number', 'no', 'num', 'cardno', 'cardnum', 'nbr'],
        ],
        'name' => [
            'label' => 'Player / card name',
            'group' => 'Card',
            'hint' => 'Dual cards like “Jordan / Pippen” become two players.',
            'aliases' => ['player', 'name', 'playername', 'cardname', 'subject', 'title', 'description'],
        ],
        'players' => [
            'label' => 'Players',
            'group' => 'Card',
            'hint' => 'Separate several players with “/” or “;”.',
            'aliases' => ['players', 'player(s)', 'playerlist'],
        ],
        'team' => [
            'label' => 'Team',
            'group' => 'Card',
            'aliases' => ['team', 'teamname', 'club'],
        ],
        'subset' => [
            'label' => 'Subset / insert',
            'group' => 'Card',
            'aliases' => ['subset', 'insert', 'insertset'],
        ],
        'parallel' => [
            'label' => 'Parallel',
            'group' => 'Card',
            'aliases' => ['parallel', 'variation', 'variant', 'parallelname', 'refractor', 'color'],
        ],
        'serial_number' => [
            'label' => 'Serial number',
            'group' => 'Card',
            'hint' => '“12” or “12/99” (the print run is picked up too).',
            'aliases' => ['serial', 'sn', 'serialnumber', 'serial#', 'serialno', 'numbered', 'serialnum'],
        ],
        'print_run' => [
            'label' => 'Print run',
            'group' => 'Card',
            'hint' => '“99” or “/99”.',
            'aliases' => ['printrun', '/', 'print', 'outof', 'numberedto', 'printrun/', 'run'],
        ],
        'is_rookie' => [
            'label' => 'Rookie',
            'group' => 'Flags',
            'hint' => 'Y, yes, x, 1, true or RC count as yes.',
            'aliases' => ['rc', 'rookie', 'rookiecard', 'rookie?', 'isrookie'],
        ],
        'is_autograph' => [
            'label' => 'Autograph',
            'group' => 'Flags',
            'aliases' => ['auto', 'autograph', 'autographed', 'signed', 'au', 'auto?'],
        ],
        'is_memorabilia' => [
            'label' => 'Relic / patch',
            'group' => 'Flags',
            'aliases' => ['relic', 'patch', 'mem', 'memorabilia', 'jersey', 'relic/patch', 'patch/relic', 'gu', 'gameused'],
        ],
        'is_short_print' => [
            'label' => 'Short print',
            'group' => 'Flags',
            'aliases' => ['sp', 'shortprint', 'ssp', 'sp/ssp'],
        ],
        'grader' => [
            'label' => 'Grading company',
            'group' => 'Condition',
            'aliases' => ['grader', 'company', 'gradingcompany', 'gradecompany', 'gradedby', 'gradingco', 'slab'],
        ],
        'grade' => [
            'label' => 'Grade',
            'group' => 'Condition',
            'hint' => 'Either “10” with a company column, or “PSA 10” on its own.',
            'aliases' => ['grade', 'gradevalue', 'score'],
        ],
        'cert_number' => [
            'label' => 'Cert number',
            'group' => 'Condition',
            'aliases' => ['cert', 'cert#', 'certnumber', 'certification', 'certno', 'certificationnumber', 'serialcert'],
        ],
        'raw_condition' => [
            'label' => 'Raw condition',
            'group' => 'Condition',
            'hint' => 'NM, NM-MT, EX, VG… Unrecognised values are left blank.',
            'aliases' => ['condition', 'rawcondition', 'cond'],
        ],
        'status' => [
            'label' => 'Status',
            'group' => 'Money',
            'hint' => 'Owned, listed, sold… Blank means owned.',
            'aliases' => ['status'],
        ],
        'purchase_price' => [
            'label' => 'Price paid',
            'group' => 'Money',
            'aliases' => ['cost', 'paid', 'pricepaid', 'purchaseprice', 'costbasis', 'boughtfor', 'amountpaid', 'buyprice'],
        ],
        'purchase_date' => [
            'label' => 'Purchase date',
            'group' => 'Money',
            'aliases' => ['purchasedate', 'datepurchased', 'bought', 'datebought', 'acquired', 'dateacquired', 'purchased', 'date'],
        ],
        'purchase_source' => [
            'label' => 'Purchased from',
            'group' => 'Money',
            'aliases' => ['source', 'purchasedfrom', 'boughtfrom', 'seller', 'purchasesource', 'from', 'where'],
        ],
        'estimated_value' => [
            'label' => 'Estimated value',
            'group' => 'Money',
            'aliases' => ['value', 'estvalue', 'estimatedvalue', 'marketvalue', 'worth', 'currentvalue', 'est', 'estimate', 'bookvalue', 'bv'],
        ],
        'location' => [
            'label' => 'Storage location',
            'group' => 'Storage',
            'hint' => 'Paths like “Closet / Box 4” are created as nested locations.',
            'aliases' => ['location', 'box', 'storage', 'storagelocation', 'binder', 'locationpath'],
        ],
        'storage_position' => [
            'label' => 'Position',
            'group' => 'Storage',
            'aliases' => ['position', 'slot', 'storageposition', 'pocket'],
        ],
        'tags' => [
            'label' => 'Tags',
            'group' => 'Storage',
            'hint' => 'Separate several tags with commas or semicolons.',
            'aliases' => ['tags', 'tag', 'labels', 'label', 'keywords'],
        ],
        'notes' => [
            'label' => 'Notes',
            'group' => 'Storage',
            'aliases' => ['notes', 'note', 'comments', 'comment', 'memo'],
        ],
        'quantity' => [
            'label' => 'Quantity',
            'group' => 'Storage',
            'hint' => 'Creates that many copies (up to 100).',
            'aliases' => ['qty', 'quantity', 'count', 'copies', 'howmany'],
        ],
    ];

    /**
     * Headers that are never guessed, even when they start with a known alias
     * (e.g. the export's yes/no "Graded" column is not a grade).
     *
     * @var list<string>
     */
    private const array NEVER_GUESS = ['id', 'graded', 'isgraded', 'created', 'createdat', 'updated', 'updatedat'];

    /**
     * Fields that must be mapped to a column before an import can start.
     *
     * @return list<string>
     */
    public static function required(): array
    {
        return array_keys(array_filter(self::FIELDS, fn (array $field): bool => $field['required'] ?? false));
    }

    /**
     * @return array<int, array{key: string, label: string, group: string, required: bool, hint: string|null}>
     */
    public static function forDisplay(): array
    {
        return collect(self::FIELDS)
            ->map(fn (array $field, string $key): array => [
                'key' => $key,
                'label' => $field['label'],
                'group' => $field['group'],
                'required' => $field['required'] ?? false,
                'hint' => $field['hint'] ?? null,
            ])
            ->values()
            ->all();
    }

    /**
     * Guess which column holds each field from the header names.
     *
     * Exact alias matches are taken first; a second pass accepts headers that start with
     * a longer alias, e.g. "Value (est.)" or "Purchase price USD".
     *
     * @param  list<string>  $headers
     * @return array<string, int|null>
     */
    public static function guessMapping(array $headers): array
    {
        $normalized = array_map(
            fn (string $header): string => in_array(self::normalize($header), self::NEVER_GUESS, true) ? '' : self::normalize($header),
            $headers,
        );
        $mapping = array_fill_keys(array_keys(self::FIELDS), null);
        $used = [];

        foreach (self::FIELDS as $key => $field) {
            foreach ($normalized as $index => $header) {
                if (! isset($used[$index]) && $header !== '' && in_array($header, $field['aliases'], true)) {
                    $mapping[$key] = $index;
                    $used[$index] = true;

                    break;
                }
            }
        }

        foreach (self::FIELDS as $key => $field) {
            if ($mapping[$key] !== null) {
                continue;
            }

            foreach ($normalized as $index => $header) {
                if (isset($used[$index]) || $header === '') {
                    continue;
                }

                $matches = collect($field['aliases'])
                    ->contains(fn (string $alias): bool => strlen($alias) >= 4 && str_starts_with($header, $alias));

                if ($matches) {
                    $mapping[$key] = $index;
                    $used[$index] = true;

                    break;
                }
            }
        }

        return $mapping;
    }

    public static function normalize(string $header): string
    {
        return preg_replace('/[^a-z0-9#\/?()]/', '', mb_strtolower(trim($header))) ?? '';
    }
}
