import { cn } from '@/lib/utils';

/**
 * A plain horizontal meter. `value` and `max` are row counts.
 */
export function ProgressBar({
    value,
    max,
    label,
    className,
}: {
    value: number;
    max: number;
    label: string;
    className?: string;
}) {
    const percent =
        max > 0 ? Math.min(100, Math.round((value / max) * 100)) : 0;

    return (
        <div
            className={cn(
                'h-2.5 w-full overflow-hidden rounded-full bg-muted',
                className,
            )}
            role="progressbar"
            aria-label={label}
            aria-valuemin={0}
            aria-valuemax={max}
            aria-valuenow={value}
        >
            <div
                className="h-full rounded-full bg-primary transition-[width] duration-500"
                style={{ width: `${percent}%` }}
            />
        </div>
    );
}
