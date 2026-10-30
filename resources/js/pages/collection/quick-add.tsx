import { Head, Link, router } from '@inertiajs/react';
import { Trash2 } from 'lucide-react';
import { useMemo, useRef, useState } from 'react';
import type {
    PickedCard,
    PickedSet,
} from '@/components/collection/card-picker';
import { TagInput } from '@/components/collection/tag-input';
import { controlClass, Field, NativeSelect } from '@/components/kit/field';
import { PageHeader } from '@/components/kit/page-header';
import { Panel } from '@/components/kit/panel';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useLookup } from '@/hooks/use-lookup';
import { cn } from '@/lib/utils';
import catalog from '@/routes/catalog';
import collection from '@/routes/collection';
import sets from '@/routes/sets';
import type { FormOptions } from '@/types';

type Row = {
    key: number;
    number: string;
    name: string;
    parallel_id: string;
    raw_condition: string;
    grader: string;
    grade: string;
    purchase_price: string;
    estimated_value: string;
    quantity: string;
};

const blankRow = (previous?: Row): Row => ({
    key: Date.now() + Math.random(),
    number: '',
    name: '',
    parallel_id: previous?.parallel_id ?? '',
    raw_condition: previous?.raw_condition ?? 'nm',
    grader: previous?.grader ?? '',
    grade: '',
    purchase_price: '',
    estimated_value: '',
    quantity: '1',
});

/**
 * Keyboard-first entry for a stack of cards from one set: type a number, press Enter, repeat.
 */
export default function QuickAdd({ options }: { options: FormOptions }) {
    const [setQuery, setSetQuery] = useState('');
    const [set, setSet] = useState<PickedSet | null>(null);
    const [draft, setDraft] = useState<Row>(blankRow());
    const [rows, setRows] = useState<Row[]>([]);
    const [locationId, setLocationId] = useState('');
    const [tags, setTags] = useState<string[]>([]);
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [processing, setProcessing] = useState(false);
    const numberInput = useRef<HTMLInputElement>(null);

    const setResults = useLookup<{ sets: PickedSet[] }>(
        !set && setQuery.trim().length >= 2
            ? catalog.lookup.sets.url({ query: { q: setQuery.trim() } })
            : null,
    );
    const checklist = useLookup<{ cards: PickedCard[] }>(
        set ? catalog.lookup.cards.url({ query: { set: set.id } }) : null,
        0,
    );
    const namesByNumber = useMemo(() => {
        const map = new Map<string, string>();
        checklist.data?.cards.forEach((card) =>
            map.set(card.number.toLowerCase(), card.name),
        );

        return map;
    }, [checklist.data]);

    const checklistName = (number: string) =>
        namesByNumber.get(number.replace(/^#/, '').trim().toLowerCase());

    function addDraft() {
        if (!draft.number.trim()) {
            return;
        }

        const name = draft.name || checklistName(draft.number) || '';
        setRows((current) => [...current, { ...draft, name }]);
        setDraft(blankRow(draft));
        numberInput.current?.focus();
    }

    function onEntryKeyDown(event: React.KeyboardEvent) {
        if (event.key === 'Enter') {
            event.preventDefault();
            addDraft();
        }
    }

    function updateRow(key: number, patch: Partial<Row>) {
        setRows((current) =>
            current.map((row) =>
                row.key === key ? { ...row, ...patch } : row,
            ),
        );
    }

    function submit() {
        if (!set) {
            return;
        }

        router.post(
            collection.quickAdd.store.url(),
            {
                card_set_id: set.id,
                storage_location_id: locationId || null,
                tags,
                rows: rows.map((row) => ({
                    number: row.number,
                    name: row.name || null,
                    parallel_id: row.parallel_id || null,
                    raw_condition: row.grader
                        ? null
                        : row.raw_condition || null,
                    grader: row.grader || null,
                    grade: row.grader ? row.grade : null,
                    purchase_price: row.purchase_price || null,
                    estimated_value: row.estimated_value || null,
                    quantity: row.quantity || 1,
                })),
            } as never,
            {
                preserveScroll: true,
                onStart: () => setProcessing(true),
                onFinish: () => setProcessing(false),
                onError: (bag) => setErrors(bag as Record<string, string>),
                onSuccess: () => {
                    setRows([]);
                    setErrors({});
                },
            },
        );
    }

    const totalCards = rows.reduce(
        (sum, row) => sum + (Number(row.quantity) || 1),
        0,
    );
    const unknownName =
        draft.number.trim() !== '' && !checklistName(draft.number);

    return (
        <>
            <Head title="Quick add" />
            <PageHeader
                title="Quick add"
                description="Working through a stack from one set? Pick the set, then type each card number and press Enter. Names fill in from the checklist."
            />

            <div className="grid gap-6 xl:grid-cols-[20rem_1fr]">
                <div className="space-y-6">
                    <Panel title="Set">
                        {set ? (
                            <div className="space-y-2">
                                <p className="font-display text-xl font-semibold">
                                    {set.full_name}
                                </p>
                                <p className="text-sm text-muted-foreground">
                                    {checklist.loading
                                        ? 'Loading checklist…'
                                        : `${namesByNumber.size.toLocaleString()} cards in the checklist`}
                                    {namesByNumber.size === 0 &&
                                        !checklist.loading && (
                                            <>
                                                {' '}
                                                ·{' '}
                                                <Link
                                                    href={sets.show(set.id)}
                                                    className="text-primary hover:underline"
                                                >
                                                    paste one
                                                </Link>
                                            </>
                                        )}
                                </p>
                                <Button
                                    variant="outline"
                                    size="sm"
                                    onClick={() => {
                                        setSet(null);
                                        setRows([]);
                                    }}
                                >
                                    Choose a different set
                                </Button>
                            </div>
                        ) : (
                            <div>
                                <Input
                                    autoFocus
                                    value={setQuery}
                                    onChange={(event) =>
                                        setSetQuery(event.target.value)
                                    }
                                    placeholder="e.g. 1990 fleer"
                                    aria-label="Search sets"
                                />
                                {setResults.data && (
                                    <ul className="mt-2 max-h-72 divide-y overflow-y-auto rounded-lg border">
                                        {setResults.data.sets.map((result) => (
                                            <li key={result.id}>
                                                <button
                                                    type="button"
                                                    onClick={() => {
                                                        setSet(result);
                                                        window.setTimeout(
                                                            () =>
                                                                numberInput.current?.focus(),
                                                            50,
                                                        );
                                                    }}
                                                    className="w-full px-3 py-2 text-left text-sm hover:bg-accent"
                                                >
                                                    {result.full_name}
                                                    <span className="block text-xs text-muted-foreground">
                                                        {result.sport_label}
                                                    </span>
                                                </button>
                                            </li>
                                        ))}
                                        {setResults.data.sets.length === 0 && (
                                            <li className="p-3 text-sm text-muted-foreground">
                                                No set found.{' '}
                                                <Link
                                                    href={sets.create()}
                                                    className="text-primary hover:underline"
                                                >
                                                    Create it
                                                </Link>
                                            </li>
                                        )}
                                    </ul>
                                )}
                            </div>
                        )}
                    </Panel>

                    <Panel title="Applies to every card">
                        <div className="grid gap-4">
                            <Field label="Storage">
                                <NativeSelect
                                    value={locationId}
                                    onChange={(event) =>
                                        setLocationId(event.target.value)
                                    }
                                >
                                    <option value="">Not placed yet</option>
                                    {options.locations.map((location) => (
                                        <option
                                            key={location.id}
                                            value={location.id}
                                        >
                                            {location.path}
                                        </option>
                                    ))}
                                </NativeSelect>
                            </Field>
                            <Field label="Tags" htmlFor="quick-tags">
                                <TagInput
                                    id="quick-tags"
                                    value={tags}
                                    onChange={setTags}
                                    suggestions={options.tags}
                                />
                            </Field>
                        </div>
                    </Panel>
                </div>

                <Panel
                    title={
                        set
                            ? `Cards to add${totalCards ? ` (${totalCards})` : ''}`
                            : 'Cards to add'
                    }
                    bodyClassName="p-0"
                >
                    {!set ? (
                        <p className="p-6 text-sm text-muted-foreground">
                            Choose a set to start entering cards.
                        </p>
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="w-full min-w-[56rem] text-sm">
                                <thead className="border-b bg-muted/50 text-left text-xs text-muted-foreground">
                                    <tr>
                                        <th className="w-24 px-2 py-2 font-medium">
                                            Card #
                                        </th>
                                        <th className="px-2 py-2 font-medium">
                                            Name
                                        </th>
                                        <th className="w-40 px-2 py-2 font-medium">
                                            Parallel
                                        </th>
                                        <th className="w-36 px-2 py-2 font-medium">
                                            Condition
                                        </th>
                                        <th className="w-24 px-2 py-2 font-medium">
                                            Paid $
                                        </th>
                                        <th className="w-24 px-2 py-2 font-medium">
                                            Value $
                                        </th>
                                        <th className="w-16 px-2 py-2 font-medium">
                                            Qty
                                        </th>
                                        <th className="w-10" />
                                    </tr>
                                </thead>
                                <tbody className="divide-y">
                                    {rows.map((row, index) => (
                                        <RowEditor
                                            key={row.key}
                                            row={row}
                                            set={set}
                                            options={options}
                                            error={
                                                errors[
                                                    `rows.${index}.number`
                                                ] ??
                                                errors[`rows.${index}.grade`]
                                            }
                                            onChange={(patch) =>
                                                updateRow(row.key, patch)
                                            }
                                            onRemove={() =>
                                                setRows((current) =>
                                                    current.filter(
                                                        (entry) =>
                                                            entry.key !==
                                                            row.key,
                                                    ),
                                                )
                                            }
                                        />
                                    ))}
                                    <tr
                                        className="bg-primary/5"
                                        onKeyDown={onEntryKeyDown}
                                    >
                                        <td className="px-2 py-2">
                                            <input
                                                ref={numberInput}
                                                value={draft.number}
                                                onChange={(event) =>
                                                    setDraft({
                                                        ...draft,
                                                        number: event.target
                                                            .value,
                                                    })
                                                }
                                                placeholder="#"
                                                className={cn(
                                                    controlClass,
                                                    'font-display text-base font-semibold',
                                                )}
                                                aria-label="Card number"
                                            />
                                        </td>
                                        <td className="px-2 py-2">
                                            <input
                                                value={
                                                    draft.name ||
                                                    checklistName(
                                                        draft.number,
                                                    ) ||
                                                    ''
                                                }
                                                onChange={(event) =>
                                                    setDraft({
                                                        ...draft,
                                                        name: event.target
                                                            .value,
                                                    })
                                                }
                                                placeholder={
                                                    unknownName
                                                        ? 'Not in checklist. Type the name'
                                                        : 'Fills from the checklist'
                                                }
                                                className={controlClass}
                                                aria-label="Card name"
                                            />
                                        </td>
                                        <RowFields
                                            row={draft}
                                            set={set}
                                            options={options}
                                            onChange={(patch) =>
                                                setDraft({ ...draft, ...patch })
                                            }
                                        />
                                        <td className="px-2 py-2">
                                            <Button
                                                size="sm"
                                                onClick={addDraft}
                                                disabled={!draft.number.trim()}
                                            >
                                                Add
                                            </Button>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                            <div className="flex flex-wrap items-center justify-between gap-3 border-t p-4">
                                <p className="text-sm text-muted-foreground">
                                    Press Enter to add a row. Parallel and
                                    condition carry over to the next card.
                                </p>
                                <Button
                                    size="lg"
                                    disabled={rows.length === 0 || processing}
                                    onClick={submit}
                                >
                                    Add {totalCards || ''}{' '}
                                    {totalCards === 1 ? 'card' : 'cards'} to
                                    collection
                                </Button>
                            </div>
                            {errors.rows && (
                                <p className="px-4 pb-4 text-sm text-destructive">
                                    {errors.rows}
                                </p>
                            )}
                        </div>
                    )}
                </Panel>
            </div>
        </>
    );
}

function RowFields({
    row,
    set,
    options,
    onChange,
}: {
    row: Row;
    set: PickedSet;
    options: FormOptions;
    onChange: (patch: Partial<Row>) => void;
}) {
    return (
        <>
            <td className="px-2 py-2">
                <NativeSelect
                    value={row.parallel_id}
                    onChange={(event) =>
                        onChange({ parallel_id: event.target.value })
                    }
                    aria-label="Parallel"
                >
                    <option value="">Base</option>
                    {set.parallels.map((parallel) => (
                        <option key={parallel.id} value={parallel.id}>
                            {parallel.name}
                        </option>
                    ))}
                </NativeSelect>
            </td>
            <td className="px-2 py-2">
                <div className="flex gap-1">
                    <NativeSelect
                        value={
                            row.grader
                                ? `grader:${row.grader}`
                                : row.raw_condition
                        }
                        onChange={(event) =>
                            event.target.value.startsWith('grader:')
                                ? onChange({
                                      grader: event.target.value.slice(7),
                                  })
                                : onChange({
                                      grader: '',
                                      raw_condition: event.target.value,
                                  })
                        }
                        aria-label="Condition"
                    >
                        <optgroup label="Raw">
                            {options.conditions.map((condition) => (
                                <option
                                    key={condition.value}
                                    value={condition.value}
                                >
                                    {condition.label}
                                </option>
                            ))}
                        </optgroup>
                        <optgroup label="Graded">
                            {options.graders.map((grader) => (
                                <option
                                    key={grader.value}
                                    value={`grader:${grader.value}`}
                                >
                                    {grader.label}
                                </option>
                            ))}
                        </optgroup>
                    </NativeSelect>
                    {row.grader && (
                        <input
                            value={row.grade}
                            onChange={(event) =>
                                onChange({ grade: event.target.value })
                            }
                            placeholder="10"
                            className={cn(controlClass, 'w-14')}
                            aria-label="Grade"
                        />
                    )}
                </div>
            </td>
            <td className="px-2 py-2">
                <input
                    value={row.purchase_price}
                    onChange={(event) =>
                        onChange({ purchase_price: event.target.value })
                    }
                    inputMode="decimal"
                    className={cn(controlClass, 'tabular')}
                    aria-label="Price paid"
                />
            </td>
            <td className="px-2 py-2">
                <input
                    value={row.estimated_value}
                    onChange={(event) =>
                        onChange({ estimated_value: event.target.value })
                    }
                    inputMode="decimal"
                    className={cn(controlClass, 'tabular')}
                    aria-label="Value"
                />
            </td>
            <td className="px-2 py-2">
                <input
                    value={row.quantity}
                    onChange={(event) =>
                        onChange({ quantity: event.target.value })
                    }
                    inputMode="numeric"
                    className={cn(controlClass, 'tabular')}
                    aria-label="Quantity"
                />
            </td>
        </>
    );
}

function RowEditor({
    row,
    set,
    options,
    error,
    onChange,
    onRemove,
}: {
    row: Row;
    set: PickedSet;
    options: FormOptions;
    error?: string;
    onChange: (patch: Partial<Row>) => void;
    onRemove: () => void;
}) {
    return (
        <tr className={cn(error && 'bg-destructive/5')}>
            <td className="px-2 py-2">
                <input
                    value={row.number}
                    onChange={(event) =>
                        onChange({ number: event.target.value })
                    }
                    className={cn(
                        controlClass,
                        'font-display text-base font-semibold',
                    )}
                    aria-label="Card number"
                />
            </td>
            <td className="px-2 py-2">
                <input
                    value={row.name}
                    onChange={(event) => onChange({ name: event.target.value })}
                    className={controlClass}
                    aria-label="Card name"
                />
                {error && (
                    <p className="mt-1 text-xs text-destructive">{error}</p>
                )}
            </td>
            <RowFields
                row={row}
                set={set}
                options={options}
                onChange={onChange}
            />
            <td className="px-2 py-2">
                <button
                    type="button"
                    onClick={onRemove}
                    className="text-muted-foreground hover:text-destructive"
                    aria-label="Remove row"
                >
                    <Trash2 className="size-4" />
                </button>
            </td>
        </tr>
    );
}
