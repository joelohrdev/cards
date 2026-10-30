import { Link } from '@inertiajs/react';
import { Check } from 'lucide-react';
import { CardArt } from '@/components/cards/card-art';
import { CardFlags } from '@/components/cards/card-flags';
import { GradeBadge } from '@/components/cards/grade-badge';
import { money } from '@/lib/format';
import { cn } from '@/lib/utils';
import collection from '@/routes/collection';
import type { CollectionItem } from '@/types';

/**
 * A card in the collection grid. Selection mode turns the tile into a toggle.
 */
export function CardTile({
    item,
    selected = false,
    selecting = false,
    onToggle,
}: {
    item: CollectionItem;
    selected?: boolean;
    selecting?: boolean;
    onToggle?: (item: CollectionItem, event: React.MouseEvent) => void;
}) {
    const body = (
        <>
            <div className="relative">
                <CardArt item={item} />
                <GradeBadge item={item} className="absolute top-2 left-2" />
                {item.status !== 'owned' && (
                    <span className="absolute right-2 bottom-2 rounded-sm bg-foreground/85 px-1.5 py-0.5 text-[0.7rem] font-semibold text-background">
                        {item.status_label}
                    </span>
                )}
                {(selecting || selected) && (
                    <span
                        className={cn(
                            'absolute top-2 right-2 flex size-6 items-center justify-center rounded-full border-2 border-white shadow',
                            selected
                                ? 'bg-primary text-primary-foreground'
                                : 'bg-black/30',
                        )}
                    >
                        {selected && (
                            <Check className="size-3.5" strokeWidth={3} />
                        )}
                    </span>
                )}
            </div>
            <div className="mt-2 space-y-0.5 px-0.5">
                <div className="flex items-start justify-between gap-2">
                    <p className="line-clamp-1 font-display text-[1.05rem] leading-tight font-semibold">
                        {item.card.name}
                    </p>
                    <p className="text-sm font-semibold tabular">
                        {money(item.estimated_value, '')}
                    </p>
                </div>
                <p className="line-clamp-1 text-xs text-muted-foreground">
                    {item.card.set.full_name}{' '}
                    <span className="text-foreground/80">
                        #{item.card.number}
                    </span>
                </p>
                <div className="flex min-h-5 flex-wrap items-center gap-1 pt-0.5">
                    {item.parallel && (
                        <span className="text-xs font-medium text-foreground/80">
                            {item.parallel.name}
                        </span>
                    )}
                    <CardFlags
                        card={item.card}
                        printRun={item.print_run}
                        serialNumber={item.serial_number}
                    />
                </div>
            </div>
        </>
    );

    const tileClass = cn(
        'group block rounded-lg p-2 text-left transition-colors outline-none hover:bg-card focus-visible:ring-2 focus-visible:ring-ring',
        selected && 'bg-primary/10 ring-2 ring-primary hover:bg-primary/10',
    );

    if (selecting) {
        return (
            <button
                type="button"
                className={cn(tileClass, 'w-full')}
                onClick={(event) => onToggle?.(item, event)}
                aria-pressed={selected}
            >
                {body}
            </button>
        );
    }

    return (
        <Link
            href={collection.show(item.id)}
            className={tileClass}
            prefetch="hover"
        >
            {body}
        </Link>
    );
}
