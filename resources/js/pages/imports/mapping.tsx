import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, Play } from 'lucide-react';
import { useMemo, useState } from 'react';
import type { ImportSummary } from '@/components/data/types';
import { Field, NativeSelect, controlClass } from '@/components/kit/field';
import { PageHeader } from '@/components/kit/page-header';
import { Panel } from '@/components/kit/panel';
import { Button } from '@/components/ui/button';
import { count } from '@/lib/format';
import { cn } from '@/lib/utils';
import imports from '@/routes/imports';
import type { LocationOption, Option } from '@/types';

type ImportField = {
    key: string;
    label: string;
    group: string;
    required: boolean;
    hint: string | null;
};

type Mapping = Record<string, number | null>;

type Defaults = {
    sport: string | null;
    tags: string[];
    storage_location_id: number | null;
};

type Props = {
    import: ImportSummary;
    headers: string[];
    preview: string[][];
    fields: ImportField[];
    mapping: Mapping;
    defaults: Defaults;
    importTagName: string;
    sports: Option[];
    locations: LocationOption[];
};

/**
 * Spreadsheet-style column letters: 0 → A, 26 → AA.
 */
function columnLetter(index: number): string {
    let letter = '';
    let remaining = index + 1;

    while (remaining > 0) {
        const modulo = (remaining - 1) % 26;
        letter = String.fromCharCode(65 + modulo) + letter;
        remaining = Math.floor((remaining - modulo) / 26);
    }

    return letter;
}

export default function ImportMapping({
    import: upload,
    headers,
    preview,
    fields,
    mapping,
    defaults,
    importTagName,
    sports,
    locations,
}: Props) {
    const form = useForm<{ mapping: Mapping; defaults: Defaults }>({
        mapping,
        defaults,
    });
    const [tagsText, setTagsText] = useState(defaults.tags.join(', '));
    const errors = form.errors as Record<string, string | undefined>;

    const groups = useMemo(
        () =>
            fields.reduce<Record<string, ImportField[]>>((grouped, field) => {
                (grouped[field.group] ??= []).push(field);

                return grouped;
            }, {}),
        [fields],
    );

    const fieldByColumn = useMemo(() => {
        const lookup: Record<number, string> = {};

        fields.forEach((field) => {
            const column = form.data.mapping[field.key];

            if (column !== null && column !== undefined) {
                lookup[column] = field.label;
            }
        });

        return lookup;
    }, [fields, form.data.mapping]);

    const mappedCount = Object.values(form.data.mapping).filter(
        (column) => column !== null,
    ).length;
    const sample = (column: number | null) => {
        if (column === null) {
            return null;
        }

        return (
            preview
                .map((row) => row[column])
                .find((value) => value && value.trim() !== '') ?? null
        );
    };

    function setColumn(key: string, value: string) {
        form.setData('mapping', {
            ...form.data.mapping,
            [key]: value === '' ? null : Number(value),
        });
    }

    function submit(event: React.FormEvent) {
        event.preventDefault();
        form.put(imports.update.url(upload.id));
    }

    return (
        <>
            <Head title="Match columns" />

            <PageHeader
                eyebrow={
                    <Link
                        href={imports.index()}
                        className="inline-flex items-center gap-1 hover:text-foreground"
                    >
                        <ArrowLeft className="size-3.5" /> Imports
                    </Link>
                }
                title="Match your columns"
                description={
                    <>
                        <span className="font-medium text-foreground">
                            {upload.original_filename}
                        </span>{' '}
                        has {count(upload.total_rows, 'card row')} and{' '}
                        {count(headers.length, 'column')}. We’ve guessed the
                        matches from your headings; check them, then start the
                        import.
                    </>
                }
            />

            <form
                onSubmit={submit}
                className="grid gap-6 lg:grid-cols-[minmax(0,1fr)_22rem]"
            >
                <div className="space-y-6">
                    {Object.entries(groups).map(([group, groupFields]) => (
                        <Panel key={group} title={group} bodyClassName="p-0">
                            <ul className="divide-y">
                                {groupFields.map((field) => {
                                    const column =
                                        form.data.mapping[field.key] ?? null;
                                    const example = sample(column);
                                    const error =
                                        errors[`mapping.${field.key}`];

                                    return (
                                        <li
                                            key={field.key}
                                            className="grid gap-2 px-4 py-3 sm:grid-cols-[12rem_minmax(0,1fr)] sm:items-start"
                                        >
                                            <div>
                                                <label
                                                    htmlFor={`map-${field.key}`}
                                                    className="text-sm font-medium"
                                                >
                                                    {field.label}
                                                    {field.required && (
                                                        <span className="ml-1 text-xs font-normal text-muted-foreground">
                                                            required
                                                        </span>
                                                    )}
                                                </label>
                                                {field.hint && (
                                                    <p className="text-xs text-muted-foreground">
                                                        {field.hint}
                                                    </p>
                                                )}
                                            </div>
                                            <div className="min-w-0">
                                                <NativeSelect
                                                    id={`map-${field.key}`}
                                                    value={
                                                        column === null
                                                            ? ''
                                                            : String(column)
                                                    }
                                                    onChange={(event) =>
                                                        setColumn(
                                                            field.key,
                                                            event.target.value,
                                                        )
                                                    }
                                                    aria-invalid={
                                                        error ? true : undefined
                                                    }
                                                    className={cn(
                                                        column === null &&
                                                            'text-muted-foreground',
                                                    )}
                                                >
                                                    <option value="">
                                                        Not in my file
                                                    </option>
                                                    {headers.map(
                                                        (header, index) => (
                                                            <option
                                                                key={index}
                                                                value={index}
                                                            >
                                                                {columnLetter(
                                                                    index,
                                                                )}{' '}
                                                                · {header}
                                                            </option>
                                                        ),
                                                    )}
                                                </NativeSelect>
                                                {error ? (
                                                    <p className="mt-1 text-sm text-destructive">
                                                        {error}
                                                    </p>
                                                ) : (
                                                    example !== null && (
                                                        <p className="mt-1 truncate text-xs text-muted-foreground">
                                                            e.g.{' '}
                                                            <span className="text-foreground/80">
                                                                {example}
                                                            </span>
                                                        </p>
                                                    )
                                                )}
                                            </div>
                                        </li>
                                    );
                                })}
                            </ul>
                        </Panel>
                    ))}
                </div>

                <div className="space-y-6 lg:sticky lg:top-24 lg:self-start">
                    <Panel title="Defaults for every row">
                        <div className="space-y-4">
                            <Field
                                label="Default sport"
                                htmlFor="default-sport"
                                error={errors['defaults.sport']}
                                hint="Used when a row has no sport, or your file has no sport column."
                            >
                                <NativeSelect
                                    id="default-sport"
                                    value={form.data.defaults.sport ?? ''}
                                    onChange={(event) =>
                                        form.setData('defaults', {
                                            ...form.data.defaults,
                                            sport: event.target.value || null,
                                        })
                                    }
                                >
                                    <option value="">Choose a sport</option>
                                    {sports.map((sport) => (
                                        <option
                                            key={sport.value}
                                            value={sport.value}
                                        >
                                            {sport.label}
                                        </option>
                                    ))}
                                </NativeSelect>
                            </Field>

                            <Field
                                label="Extra tags"
                                htmlFor="default-tags"
                                error={errors['defaults.tags']}
                                hint={`Separate with commas. Every card also gets “${importTagName}”.`}
                            >
                                <input
                                    id="default-tags"
                                    className={controlClass}
                                    value={tagsText}
                                    placeholder="e.g. Shoebox, To sort"
                                    onChange={(event) => {
                                        setTagsText(event.target.value);
                                        form.setData('defaults', {
                                            ...form.data.defaults,
                                            tags: event.target.value
                                                .split(',')
                                                .map((tag) => tag.trim())
                                                .filter(Boolean),
                                        });
                                    }}
                                />
                            </Field>

                            <Field
                                label="Default storage location"
                                htmlFor="default-location"
                                error={errors['defaults.storage_location_id']}
                                hint="Used when a row has no location of its own."
                            >
                                <NativeSelect
                                    id="default-location"
                                    value={
                                        form.data.defaults
                                            .storage_location_id ?? ''
                                    }
                                    onChange={(event) =>
                                        form.setData('defaults', {
                                            ...form.data.defaults,
                                            storage_location_id:
                                                event.target.value === ''
                                                    ? null
                                                    : Number(
                                                          event.target.value,
                                                      ),
                                        })
                                    }
                                >
                                    <option value="">None</option>
                                    {locations.map((location) => (
                                        <option
                                            key={location.id}
                                            value={location.id}
                                        >
                                            {location.path}
                                        </option>
                                    ))}
                                </NativeSelect>
                            </Field>
                        </div>
                    </Panel>

                    <div className="rounded-lg border bg-card p-4">
                        <p className="text-sm text-muted-foreground">
                            <span className="font-display text-2xl font-bold text-foreground tabular">
                                {mappedCount}
                            </span>{' '}
                            of {headers.length} columns matched. Unmatched
                            columns are ignored.
                        </p>
                        <p className="mt-2 text-sm text-muted-foreground">
                            Rows that can’t be read are skipped and listed
                            afterwards; the rest still import.
                        </p>
                        <Button
                            type="submit"
                            className="mt-4 w-full"
                            disabled={form.processing}
                        >
                            <Play />{' '}
                            {form.processing
                                ? 'Starting…'
                                : `Import ${count(upload.total_rows, 'row')}`}
                        </Button>
                    </div>
                </div>
            </form>

            <Panel
                title="First rows of your file"
                className="mt-6"
                bodyClassName="p-0"
            >
                <div className="overflow-x-auto">
                    <table className="w-full text-sm">
                        <thead className="border-b bg-muted/50 text-left text-xs">
                            <tr>
                                {headers.map((header, index) => (
                                    <th
                                        key={index}
                                        className="px-3 py-2 align-bottom font-medium whitespace-nowrap"
                                    >
                                        <span
                                            className={cn(
                                                'block text-[0.7rem]',
                                                fieldByColumn[index]
                                                    ? 'text-primary'
                                                    : 'text-muted-foreground/70',
                                            )}
                                        >
                                            {fieldByColumn[index]
                                                ? `→ ${fieldByColumn[index]}`
                                                : 'Ignored'}
                                        </span>
                                        <span className="text-muted-foreground">
                                            {columnLetter(index)} ·{' '}
                                        </span>
                                        {header}
                                    </th>
                                ))}
                            </tr>
                        </thead>
                        <tbody className="divide-y">
                            {preview.map((row, rowIndex) => (
                                <tr key={rowIndex}>
                                    {headers.map((_, index) => (
                                        <td
                                            key={index}
                                            className={cn(
                                                'max-w-56 truncate px-3 py-2 whitespace-nowrap',
                                                !fieldByColumn[index] &&
                                                    'text-muted-foreground/70',
                                            )}
                                        >
                                            {row[index] ?? ''}
                                        </td>
                                    ))}
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </Panel>
        </>
    );
}
