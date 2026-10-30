import { CardArt } from '@/components/cards/card-art';
import { cn } from '@/lib/utils';
import type { CollectionItem } from '@/types';

export function CardThumb({
    item,
    className,
}: {
    item: Pick<CollectionItem, 'image' | 'card' | 'parallel'>;
    className?: string;
}) {
    return (
        <div className={cn('w-10 shrink-0', className)}>
            <CardArt item={item} size="sm" />
        </div>
    );
}
