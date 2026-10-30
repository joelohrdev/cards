import { Link } from '@inertiajs/react';
import { cn } from '@/lib/utils';
import type { Paginated } from '@/types';

export function Pagination({ page }: { page: Paginated<unknown> }) {
    if (page.last_page <= 1) {
        return null;
    }

    return (
        <nav
            className="no-print mt-8 flex flex-wrap items-center justify-between gap-3"
            aria-label="Pagination"
        >
            <p className="text-sm text-muted-foreground">
                Showing {page.from?.toLocaleString()}–
                {page.to?.toLocaleString()} of {page.total.toLocaleString()}
            </p>
            <div className="flex flex-wrap gap-1">
                {page.links.map((link, index) => {
                    const label = link.label
                        .replace('&laquo; Previous', '‹ Prev')
                        .replace('Next &raquo;', 'Next ›');

                    return link.url ? (
                        <Link
                            key={`${link.label}-${index}`}
                            href={link.url}
                            preserveScroll={false}
                            className={cn(
                                'flex h-9 min-w-9 items-center justify-center rounded-md border bg-card px-3 text-sm tabular hover:bg-accent',
                                link.active &&
                                    'border-primary bg-primary text-primary-foreground hover:bg-primary',
                            )}
                        >
                            {label}
                        </Link>
                    ) : (
                        <span
                            key={`${link.label}-${index}`}
                            className="flex h-9 min-w-9 items-center justify-center px-2 text-sm text-muted-foreground"
                        >
                            {label}
                        </span>
                    );
                })}
            </div>
        </nav>
    );
}
