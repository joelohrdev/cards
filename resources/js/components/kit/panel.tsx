import { cn } from '@/lib/utils';

export function Panel({
    title,
    actions,
    children,
    className,
    bodyClassName,
}: {
    title?: React.ReactNode;
    actions?: React.ReactNode;
    children: React.ReactNode;
    className?: string;
    bodyClassName?: string;
}) {
    return (
        <section className={cn('rounded-lg border bg-card', className)}>
            {(title || actions) && (
                <div className="flex items-center justify-between gap-3 border-b px-4 py-3">
                    {title && (
                        <h2 className="font-display text-lg leading-none font-semibold">
                            {title}
                        </h2>
                    )}
                    {actions && (
                        <div className="flex items-center gap-2">{actions}</div>
                    )}
                </div>
            )}
            <div className={cn('p-4', bodyClassName)}>{children}</div>
        </section>
    );
}
