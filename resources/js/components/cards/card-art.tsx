import { cn } from '@/lib/utils';
import type { CollectionItem } from '@/types';

/**
 * A card-shaped (2.5 × 3.5) frame showing the front photo, or a printed
 * card-back placeholder with the number and name when there is no photo.
 */
export function CardArt({
    item,
    className,
    size = 'md',
}: {
    item: Pick<CollectionItem, 'image' | 'card' | 'parallel'>;
    className?: string;
    size?: 'sm' | 'md' | 'lg';
}) {
    const hasParallel = item.parallel !== null;

    return (
        <div
            className={cn(
                'relative aspect-[5/7] w-full overflow-hidden rounded-[6%/4.3%]',
                hasParallel
                    ? 'refractor-edge p-[3px] refractor'
                    : 'bg-border p-px',
                className,
            )}
        >
            <div className="relative h-full w-full overflow-hidden rounded-[5%/3.6%] bg-card">
                {item.image ? (
                    <img
                        src={item.image}
                        alt={`${item.card.name} card`}
                        loading="lazy"
                        className="h-full w-full object-cover"
                    />
                ) : (
                    <CardBack item={item} size={size} />
                )}
            </div>
        </div>
    );
}

function CardBack({
    item,
    size,
}: {
    item: Pick<CollectionItem, 'card'>;
    size: 'sm' | 'md' | 'lg';
}) {
    if (size === 'sm') {
        return (
            <div className="flex h-full w-full items-center justify-center bg-muted">
                <span className="font-display text-[0.7rem] font-bold text-muted-foreground">
                    {item.card.number.slice(0, 4)}
                </span>
            </div>
        );
    }

    return (
        <div className="flex h-full w-full flex-col bg-muted p-[8%] text-foreground">
            <div className="flex items-start justify-between gap-2">
                <span
                    className={cn(
                        'font-display leading-none font-bold',
                        size === 'lg' ? 'text-5xl' : 'text-3xl',
                    )}
                >
                    {item.card.number}
                </span>
                <span className="font-display text-xs font-semibold text-muted-foreground">
                    {item.card.set.year}
                </span>
            </div>
            <div className="mt-auto space-y-1">
                <div className="h-px bg-foreground/15" />
                <p
                    className={cn(
                        'line-clamp-2 font-display leading-tight font-semibold',
                        size === 'lg' ? 'text-2xl' : 'text-base',
                    )}
                >
                    {item.card.name}
                </p>
                <p className="line-clamp-1 text-[0.7rem] text-muted-foreground">
                    {item.card.set.manufacturer} {item.card.set.name}
                </p>
            </div>
        </div>
    );
}
