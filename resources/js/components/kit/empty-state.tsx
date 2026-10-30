import { cn } from '@/lib/utils';

export function EmptyState({
    title,
    description,
    action,
    className,
}: {
    title: string;
    description?: React.ReactNode;
    action?: React.ReactNode;
    className?: string;
}) {
    return (
        <div
            className={cn(
                'flex flex-col items-center rounded-lg border border-dashed bg-card/50 px-6 py-14 text-center',
                className,
            )}
        >
            <div className="mb-4 flex gap-1.5" aria-hidden="true">
                <span className="block h-12 w-9 -rotate-6 rounded-[3px] border-2 border-foreground/15 bg-card" />
                <span className="block h-12 w-9 rounded-[3px] border-2 border-foreground/25 bg-card" />
                <span className="block h-12 w-9 rotate-6 rounded-[3px] border-2 border-foreground/15 bg-card" />
            </div>
            <h3 className="font-display text-xl font-semibold">{title}</h3>
            {description && (
                <p className="mt-1 max-w-md text-sm text-muted-foreground">
                    {description}
                </p>
            )}
            {action && <div className="mt-5">{action}</div>}
        </div>
    );
}
