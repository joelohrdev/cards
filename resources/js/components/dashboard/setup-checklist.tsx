import { Link } from '@inertiajs/react';
import type { InertiaLinkProps } from '@inertiajs/react';
import { Check, ChevronRight, Plus, Rows3 } from 'lucide-react';
import { Panel } from '@/components/kit/panel';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import collection from '@/routes/collection';
import ebay from '@/routes/ebay';
import imports from '@/routes/imports';
import locations from '@/routes/locations';
import sets from '@/routes/sets';
import tags from '@/routes/tags';
import team from '@/routes/team';

export type SetupStep = {
    key: 'team' | 'storage' | 'tags' | 'sets' | 'import' | 'ebay';
    done: boolean;
};

const STEP_DETAILS: Record<
    SetupStep['key'],
    {
        title: string;
        detail: string;
        action: string;
        href: NonNullable<InertiaLinkProps['href']>;
        optional?: boolean;
    }
> = {
    team: {
        title: 'Invite your team',
        detail: 'Each person gets an email link to set up their account.',
        action: 'Invite',
        href: team.index(),
    },
    storage: {
        title: 'Set up storage',
        detail: 'Add your boxes, binders and cases so every card gets a place.',
        action: 'Add storage',
        href: locations.index(),
    },
    tags: {
        title: 'Create tags',
        detail: 'Labels you’ll sort by, like PC, Flip or Grading candidates.',
        action: 'Add tags',
        href: tags.index(),
    },
    sets: {
        title: 'Add the sets you collect',
        detail: 'Paste each set’s checklist so cards can be searched for instead of typed in.',
        action: 'Add a set',
        href: sets.create(),
    },
    import: {
        title: 'Import a spreadsheet',
        detail: 'Already tracking cards somewhere else? Bring them in.',
        action: 'Import',
        href: imports.index(),
        optional: true,
    },
    ebay: {
        title: 'Connect eBay',
        detail: 'Only needed to sell. Each person connects their own account.',
        action: 'Connect',
        href: ebay.settings(),
        optional: true,
    },
};

/**
 * What to set up before adding cards, shown on the Overview while the collection is empty.
 * Each step ticks itself off once its records exist.
 */
export function SetupChecklist({ steps }: { steps: SetupStep[] }) {
    const required = steps.filter((step) => !STEP_DETAILS[step.key].optional);
    const doneCount = required.filter((step) => step.done).length;

    return (
        <div className="space-y-6">
            <Panel
                title="Get set up"
                actions={
                    <span className="text-sm text-muted-foreground tabular">
                        {doneCount} of {required.length} done
                    </span>
                }
                bodyClassName="p-0"
            >
                <ol className="divide-y">
                    {steps.map((step, index) => {
                        const details = STEP_DETAILS[step.key];

                        return (
                            <li
                                key={step.key}
                                className="flex items-center gap-4 px-4 py-4"
                            >
                                <span
                                    className={cn(
                                        'flex size-8 shrink-0 items-center justify-center rounded-full font-display text-sm font-bold',
                                        step.done
                                            ? 'bg-primary text-primary-foreground'
                                            : 'bg-muted text-muted-foreground',
                                    )}
                                    aria-label={
                                        step.done ? 'Done' : `Step ${index + 1}`
                                    }
                                >
                                    {step.done ? (
                                        <Check
                                            className="size-4"
                                            strokeWidth={3}
                                        />
                                    ) : (
                                        index + 1
                                    )}
                                </span>
                                <div className="min-w-0 flex-1">
                                    <p
                                        className={cn(
                                            'flex flex-wrap items-center gap-2 font-medium',
                                            step.done &&
                                                'text-muted-foreground line-through',
                                        )}
                                    >
                                        {details.title}
                                        {details.optional && (
                                            <span className="rounded-sm bg-muted px-1.5 py-0.5 text-xs font-normal text-muted-foreground no-underline">
                                                Optional
                                            </span>
                                        )}
                                    </p>
                                    {!step.done && (
                                        <p className="text-sm text-muted-foreground">
                                            {details.detail}
                                        </p>
                                    )}
                                </div>
                                {!step.done && (
                                    <Button
                                        asChild
                                        variant="outline"
                                        className="shrink-0"
                                    >
                                        <Link href={details.href}>
                                            {details.action}
                                            <ChevronRight />
                                        </Link>
                                    </Button>
                                )}
                            </li>
                        );
                    })}
                </ol>
            </Panel>

            <div className="flex flex-wrap items-center gap-2">
                <Button asChild>
                    <Link href={collection.create()}>
                        <Plus /> Add a card
                    </Link>
                </Button>
                <Button asChild variant="outline">
                    <Link href={collection.quickAdd()}>
                        <Rows3 /> Quick add a stack
                    </Link>
                </Button>
            </div>
        </div>
    );
}
