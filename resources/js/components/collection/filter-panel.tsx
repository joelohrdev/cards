import { NativeSelect } from '@/components/kit/field';
import { tagDot } from '@/components/cards/tag-chip';
import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';
import type { FormOptions, Option } from '@/types';

export type CollectionFilters = Partial<{
    sport: string;
    year_from: string;
    year_to: string;
    set: string;
    player: string;
    team: string;
    tag: string[];
    location: string;
    status: string[];
    grader: string;
    graded: string;
    rookie: string;
    autograph: string;
    memorabilia: string;
    numbered: string;
    min_value: string;
    max_value: string;
    has_image: string;
    has_value: string;
    sort: string;
}>;

function Section({
    title,
    children,
}: {
    title: string;
    children: React.ReactNode;
}) {
    return (
        <fieldset className="space-y-2 border-b pb-4 last:border-0">
            <legend className="mb-2 text-sm font-semibold">{title}</legend>
            {children}
        </fieldset>
    );
}

function ToggleChip({
    active,
    onClick,
    children,
}: {
    active: boolean;
    onClick: () => void;
    children: React.ReactNode;
}) {
    return (
        <button
            type="button"
            onClick={onClick}
            aria-pressed={active}
            className={cn(
                'rounded-full border px-2.5 py-1 text-sm transition-colors',
                active
                    ? 'border-primary bg-primary text-primary-foreground'
                    : 'bg-card hover:bg-accent',
            )}
        >
            {children}
        </button>
    );
}

export function FilterPanel({
    filters,
    options,
    sets,
    onChange,
}: {
    filters: CollectionFilters;
    options: FormOptions;
    sets: Option[];
    onChange: (next: CollectionFilters) => void;
}) {
    const set = (
        key: keyof CollectionFilters,
        value: string | string[] | undefined,
    ) => onChange({ ...filters, [key]: value === '' ? undefined : value });

    const toggleArray = (key: 'tag' | 'status', value: string) => {
        const current = filters[key] ?? [];
        const next = current.includes(value)
            ? current.filter((entry) => entry !== value)
            : [...current.filter((entry) => entry !== 'all'), value];
        set(key, next.length ? next : undefined);
    };

    const flag = (
        key: 'rookie' | 'autograph' | 'memorabilia' | 'numbered',
        label: string,
    ) => (
        <ToggleChip
            active={filters[key] === '1'}
            onClick={() => set(key, filters[key] === '1' ? undefined : '1')}
        >
            {label}
        </ToggleChip>
    );

    return (
        <div className="space-y-4">
            <Section title="Status">
                <div className="flex flex-wrap gap-1.5">
                    <ToggleChip
                        active={!filters.status?.length}
                        onClick={() => set('status', undefined)}
                    >
                        In hand
                    </ToggleChip>
                    {options.statuses.map((status) => (
                        <ToggleChip
                            key={status.value}
                            active={
                                filters.status?.includes(status.value) ?? false
                            }
                            onClick={() => toggleArray('status', status.value)}
                        >
                            {status.label}
                        </ToggleChip>
                    ))}
                    <ToggleChip
                        active={filters.status?.includes('all') ?? false}
                        onClick={() => set('status', ['all'])}
                    >
                        Everything
                    </ToggleChip>
                </div>
            </Section>

            <Section title="Card">
                <div className="flex flex-wrap gap-1.5">
                    {flag('rookie', 'Rookies')}
                    {flag('autograph', 'Autos')}
                    {flag('memorabilia', 'Relics')}
                    {flag('numbered', 'Numbered')}
                </div>
            </Section>

            <Section title="Sport">
                <NativeSelect
                    value={filters.sport ?? ''}
                    onChange={(event) => set('sport', event.target.value)}
                    aria-label="Sport"
                >
                    <option value="">Any sport</option>
                    {options.sports.map((sport) => (
                        <option key={sport.value} value={sport.value}>
                            {sport.label}
                        </option>
                    ))}
                </NativeSelect>
            </Section>

            <Section title="Year">
                <div className="flex items-center gap-2">
                    <Input
                        inputMode="numeric"
                        placeholder="From"
                        defaultValue={filters.year_from ?? ''}
                        onBlur={(event) => set('year_from', event.target.value)}
                        aria-label="Year from"
                    />
                    <span className="text-muted-foreground">–</span>
                    <Input
                        inputMode="numeric"
                        placeholder="To"
                        defaultValue={filters.year_to ?? ''}
                        onBlur={(event) => set('year_to', event.target.value)}
                        aria-label="Year to"
                    />
                </div>
            </Section>

            {sets.length > 0 && (
                <Section title="Set">
                    <NativeSelect
                        value={filters.set ?? ''}
                        onChange={(event) => set('set', event.target.value)}
                        aria-label="Set"
                    >
                        <option value="">Any set</option>
                        {sets.map((cardSet) => (
                            <option key={cardSet.value} value={cardSet.value}>
                                {cardSet.label}
                            </option>
                        ))}
                    </NativeSelect>
                </Section>
            )}

            <Section title="Condition">
                <div className="flex flex-wrap gap-1.5">
                    <ToggleChip
                        active={filters.graded === 'graded'}
                        onClick={() =>
                            set(
                                'graded',
                                filters.graded === 'graded'
                                    ? undefined
                                    : 'graded',
                            )
                        }
                    >
                        Graded
                    </ToggleChip>
                    <ToggleChip
                        active={filters.graded === 'raw'}
                        onClick={() =>
                            set(
                                'graded',
                                filters.graded === 'raw' ? undefined : 'raw',
                            )
                        }
                    >
                        Raw
                    </ToggleChip>
                </div>
                <NativeSelect
                    value={filters.grader ?? ''}
                    onChange={(event) => set('grader', event.target.value)}
                    aria-label="Grader"
                >
                    <option value="">Any grader</option>
                    {options.graders.map((grader) => (
                        <option key={grader.value} value={grader.value}>
                            {grader.label}
                        </option>
                    ))}
                </NativeSelect>
            </Section>

            {options.tags.length > 0 && (
                <Section title="Tags">
                    <div className="flex flex-wrap gap-1.5">
                        {options.tags.map((tag) => (
                            <ToggleChip
                                key={tag.id}
                                active={
                                    filters.tag?.includes(String(tag.id)) ??
                                    false
                                }
                                onClick={() =>
                                    toggleArray('tag', String(tag.id))
                                }
                            >
                                <span
                                    className={cn(
                                        'mr-1.5 inline-block size-2 rounded-full',
                                        tagDot[tag.color] ?? tagDot.slate,
                                    )}
                                />
                                {tag.name}
                            </ToggleChip>
                        ))}
                    </div>
                </Section>
            )}

            <Section title="Storage">
                <NativeSelect
                    value={filters.location ?? ''}
                    onChange={(event) => set('location', event.target.value)}
                    aria-label="Storage location"
                >
                    <option value="">Anywhere</option>
                    <option value="none">Not placed yet</option>
                    {options.locations.map((location) => (
                        <option key={location.id} value={String(location.id)}>
                            {'  '.repeat(location.depth)}
                            {location.name}
                        </option>
                    ))}
                </NativeSelect>
            </Section>

            <Section title="Value">
                <div className="flex items-center gap-2">
                    <Input
                        inputMode="decimal"
                        placeholder="$ min"
                        defaultValue={filters.min_value ?? ''}
                        onBlur={(event) => set('min_value', event.target.value)}
                        aria-label="Minimum value"
                    />
                    <span className="text-muted-foreground">–</span>
                    <Input
                        inputMode="decimal"
                        placeholder="$ max"
                        defaultValue={filters.max_value ?? ''}
                        onBlur={(event) => set('max_value', event.target.value)}
                        aria-label="Maximum value"
                    />
                </div>
            </Section>
        </div>
    );
}
