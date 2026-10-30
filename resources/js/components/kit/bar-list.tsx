import { Link } from '@inertiajs/react';
import type { InertiaLinkProps } from '@inertiajs/react';

type Row = {
    key: string;
    label: string;
    value: number;
    display: string;
    detail?: string;
    href?: NonNullable<InertiaLinkProps['href']>;
};

/**
 * Horizontal magnitude bars in a single hue, labelled directly.
 */
export function BarList({ rows }: { rows: Row[] }) {
    const max = Math.max(1, ...rows.map((row) => row.value));

    return (
        <ul className="space-y-2.5">
            {rows.map((row) => {
                const content = (
                    <>
                        <div className="flex items-baseline justify-between gap-3 text-sm">
                            <span className="truncate font-medium">
                                {row.label}
                            </span>
                            <span className="shrink-0 tabular">
                                {row.display}
                                {row.detail && (
                                    <span className="ml-1.5 text-muted-foreground">
                                        {row.detail}
                                    </span>
                                )}
                            </span>
                        </div>
                        <div className="mt-1 h-2 rounded-full bg-muted">
                            <div
                                className="h-2 rounded-full bg-chart-1"
                                style={{
                                    width: `${Math.max(2, (row.value / max) * 100)}%`,
                                }}
                                title={`${row.label}: ${row.display}`}
                            />
                        </div>
                    </>
                );

                return (
                    <li key={row.key}>
                        {row.href ? (
                            <Link
                                href={row.href}
                                className="block rounded-md hover:opacity-80"
                            >
                                {content}
                            </Link>
                        ) : (
                            content
                        )}
                    </li>
                );
            })}
        </ul>
    );
}
