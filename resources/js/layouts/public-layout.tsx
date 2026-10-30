import { usePage } from '@inertiajs/react';
import { LogoMark } from '@/components/shell/logo-mark';

/**
 * The frame for pages anyone can open without signing in (share-link showcases).
 * No navigation, no account menu: just the brand band and the content.
 */
export default function PublicLayout({
    children,
}: {
    children: React.ReactNode;
}) {
    const { name } = usePage<{ name: string }>().props;

    return (
        <div className="flex min-h-svh flex-col bg-background">
            <header className="no-print bg-rail text-rail-foreground">
                <div className="mx-auto flex h-14 max-w-[1400px] items-center gap-3 px-4 sm:px-6">
                    <LogoMark className="h-7 text-rail-foreground" />
                    <span className="font-display text-lg leading-none font-bold">
                        {name}
                    </span>
                    <span className="ml-auto text-sm text-rail-muted">
                        Read-only collection
                    </span>
                </div>
            </header>

            <main className="flex-1">{children}</main>

            <footer className="no-print border-t">
                <div className="mx-auto max-w-[1400px] px-4 py-6 text-sm text-muted-foreground sm:px-6">
                    Shared from {name}. Only what the owner chose to share is
                    shown here.
                </div>
            </footer>
        </div>
    );
}
