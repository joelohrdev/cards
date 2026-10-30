import { cn } from '@/lib/utils';

export function PageHeader({
    title,
    description,
    actions,
    eyebrow,
    className,
}: {
    title: React.ReactNode;
    description?: React.ReactNode;
    actions?: React.ReactNode;
    eyebrow?: React.ReactNode;
    className?: string;
}) {
    return (
        <header
            className={cn(
                'mb-6 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between',
                className,
            )}
        >
            <div className="min-w-0">
                {eyebrow && (
                    <div className="mb-1 text-sm text-muted-foreground">
                        {eyebrow}
                    </div>
                )}
                <h1 className="font-display text-3xl leading-tight font-bold sm:text-4xl">
                    {title}
                </h1>
                {description && (
                    <p className="mt-1 max-w-2xl text-muted-foreground">
                        {description}
                    </p>
                )}
            </div>
            {actions && (
                <div className="no-print flex flex-wrap items-center gap-2">
                    {actions}
                </div>
            )}
        </header>
    );
}
