import { cn } from '@/lib/utils';
import type { CollectionItem } from '@/types';

/**
 * Graded cards get a slab-label chip (grader + big grade); raw cards get a quiet condition chip.
 */
export function GradeBadge({
    item,
    className,
}: {
    item: Pick<
        CollectionItem,
        'is_graded' | 'grader' | 'grade' | 'condition_label'
    >;
    className?: string;
}) {
    if (item.is_graded) {
        return (
            <span
                className={cn(
                    'inline-flex items-stretch overflow-hidden rounded-sm border border-slab bg-white text-[0.7rem] leading-none font-bold shadow-sm',
                    className,
                )}
                title={`${item.grader} ${item.grade}`}
            >
                <span className="flex items-center bg-slab px-1.5 py-1 text-slab-foreground">
                    {item.grader}
                </span>
                <span className="flex items-center px-1.5 font-display text-sm text-[#15213a]">
                    {item.grade}
                </span>
            </span>
        );
    }

    if (!item.condition_label) {
        return null;
    }

    return (
        <span
            className={cn(
                'inline-flex items-center rounded-sm bg-card/90 px-1.5 py-1 text-[0.7rem] leading-none font-medium text-muted-foreground ring-1 ring-border',
                className,
            )}
        >
            {item.condition_label}
        </span>
    );
}
