import { Head, router } from '@inertiajs/react';
import { Check } from 'lucide-react';
import { useState } from 'react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { count } from '@/lib/format';
import { cn } from '@/lib/utils';
import tidyNames from '@/routes/tidy-names';

type DuplicateRecord = {
    id: string;
    name: string;
    detail: string | null;
    uses: number;
};

type DuplicateGroup = {
    type: string;
    label: string;
    signature: string;
    records: DuplicateRecord[];
};

/**
 * What a merge does to each kind of name, shown under the group.
 */
const MERGE_NOTES: Record<string, string> = {
    manufacturer:
        'Sets move to the kept brand. A set the kept brand already has is merged with its twin.',
    set: 'Cards move to the kept set. Cards with the same number become one card, and parallels with the same name become one parallel.',
    parallel: 'Copies, comps and want list entries move to the kept parallel.',
    player: 'Cards list the kept player instead.',
    team: 'Cards use the kept team instead.',
    tag: 'Cards, share links and imports use the kept tag instead.',
};

export default function TidyNames({ groups }: { groups: DuplicateGroup[] }) {
    const labels = [...new Set(groups.map((group) => group.label))];

    return (
        <>
            <Head title="Tidy up names" />

            <div className="space-y-6">
                <Heading
                    variant="small"
                    title="Tidy up names"
                    description="Names that are in the app more than once with slightly different spelling, like “Ken Griffey Jr” and “Ken Griffey Jr.”. Pick the spelling to keep and merge the rest into it. Merging can’t be undone."
                />

                {groups.length === 0 ? (
                    <p className="flex items-center gap-2 rounded-lg border px-4 py-6 text-sm text-muted-foreground">
                        <Check className="size-4 text-gain" />
                        No duplicates. Every name is spelled one way.
                    </p>
                ) : (
                    labels.map((label) => {
                        const inLabel = groups.filter(
                            (group) => group.label === label,
                        );

                        return (
                            <section key={label} className="space-y-3">
                                <h3 className="text-sm font-semibold">
                                    {label}{' '}
                                    <span className="font-normal text-muted-foreground">
                                        ({inLabel.length})
                                    </span>
                                </h3>
                                {inLabel.map((group) => (
                                    <GroupCard
                                        key={`${group.type}:${group.signature}`}
                                        group={group}
                                    />
                                ))}
                            </section>
                        );
                    })
                )}
            </div>
        </>
    );
}

function GroupCard({ group }: { group: DuplicateGroup }) {
    const [keep, setKeep] = useState(group.records[0]?.id ?? '');
    const [merging, setMerging] = useState(false);
    const kept = group.records.find((record) => record.id === keep);
    const others = group.records.filter((record) => record.id !== keep);

    function merge() {
        router.post(
            tidyNames.store.url(),
            {
                type: group.type,
                keep,
                merge: others.map((record) => record.id),
            },
            {
                preserveScroll: true,
                onStart: () => setMerging(true),
                onFinish: () => setMerging(false),
            },
        );
    }

    return (
        <div className="rounded-lg border">
            <fieldset className="divide-y">
                <legend className="sr-only">Spelling to keep</legend>
                {group.records.map((record) => (
                    <label
                        key={record.id}
                        className={cn(
                            'flex cursor-pointer items-center gap-3 px-4 py-2.5 text-sm',
                            record.id === keep && 'bg-primary/5',
                        )}
                    >
                        <input
                            type="radio"
                            name={`${group.type}:${group.signature}`}
                            checked={record.id === keep}
                            onChange={() => setKeep(record.id)}
                            className="size-4 accent-[var(--primary)]"
                        />
                        <span className="min-w-0 flex-1">
                            <span className="block font-medium break-words">
                                {record.name}
                            </span>
                            {record.detail && (
                                <span className="block text-xs text-muted-foreground">
                                    {record.detail}
                                </span>
                            )}
                        </span>
                        <span className="text-xs text-muted-foreground tabular">
                            {count(record.uses, 'use')}
                        </span>
                    </label>
                ))}
            </fieldset>
            <div className="flex flex-wrap items-center justify-between gap-3 border-t bg-muted/40 px-4 py-2.5">
                <p className="text-xs text-muted-foreground">
                    {MERGE_NOTES[group.type] ??
                        'Every use changes to the kept spelling.'}
                </p>
                <Button size="sm" onClick={merge} disabled={merging || !kept}>
                    Merge into “{kept?.name}”
                </Button>
            </div>
        </div>
    );
}

TidyNames.layout = {
    breadcrumbs: [{ title: 'Tidy up names', href: tidyNames.index() }],
};
