import { Link, router, usePage } from '@inertiajs/react';
import {
    ChevronDown,
    LogOut,
    Menu,
    Plus,
    Search,
    Settings,
} from 'lucide-react';
import { useEffect, useState } from 'react';
import { CommandSearch } from '@/components/shell/command-search';
import { LogoMark } from '@/components/shell/logo-mark';
import { navGroups } from '@/components/shell/nav-items';
import type { ShellNavGroup } from '@/components/shell/nav-items';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Sheet, SheetContent, SheetTitle } from '@/components/ui/sheet';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { cn, toUrl } from '@/lib/utils';
import { dashboard, logout } from '@/routes';
import collection from '@/routes/collection';
import { edit as editProfile } from '@/routes/profile';

/**
 * The href of the most specific nav item matching the current page.
 */
function useActiveHref(): string | undefined {
    const { isCurrentOrParentUrl } = useCurrentUrl();

    return navGroups
        .flatMap((group) => group.items)
        .map((item) => toUrl(item.href))
        .filter((href) => isCurrentOrParentUrl(href))
        .sort((a, b) => b.length - a.length)[0];
}

const isGroupActive = (group: ShellNavGroup, activeHref?: string): boolean =>
    group.items.some((item) => toUrl(item.href) === activeHref);

const headerLinkClass =
    'relative flex h-9 items-center gap-1 rounded-md px-3 text-sm font-medium text-rail-foreground/80 transition-colors hover:bg-rail-active hover:text-rail-foreground data-[state=open]:bg-rail-active data-[state=open]:text-rail-foreground';

const headerActiveClass =
    'text-rail-foreground after:absolute after:inset-x-3 after:-bottom-[13px] after:h-0.5 after:rounded-full after:bg-sidebar-primary';

function HeaderNav() {
    const activeHref = useActiveHref();

    return (
        <nav className="hidden items-center gap-0.5 lg:flex" aria-label="Main">
            {navGroups.map((group) => {
                const active = isGroupActive(group, activeHref);

                if (group.items.length === 1) {
                    const [item] = group.items;

                    return (
                        <Link
                            key={group.title}
                            href={item.href}
                            prefetch
                            className={cn(
                                headerLinkClass,
                                active && headerActiveClass,
                            )}
                        >
                            {group.title}
                        </Link>
                    );
                }

                return (
                    <DropdownMenu key={group.title}>
                        <DropdownMenuTrigger
                            className={cn(
                                headerLinkClass,
                                'outline-none',
                                active && headerActiveClass,
                            )}
                        >
                            {group.title}
                            <ChevronDown className="size-3.5 opacity-60" />
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="start" className="w-56">
                            {group.items.map((item) => (
                                <DropdownMenuItem
                                    key={item.title}
                                    asChild
                                    className={cn(
                                        toUrl(item.href) === activeHref &&
                                            'bg-accent font-medium',
                                    )}
                                >
                                    <Link href={item.href} prefetch>
                                        <item.icon />
                                        {item.title}
                                    </Link>
                                </DropdownMenuItem>
                            ))}
                        </DropdownMenuContent>
                    </DropdownMenu>
                );
            })}
        </nav>
    );
}

function UserMenu() {
    const { auth } = usePage().props;

    return (
        <DropdownMenu>
            <DropdownMenuTrigger
                className="flex size-9 shrink-0 items-center justify-center rounded-full bg-rail-active font-display text-sm font-bold text-rail-foreground outline-none hover:ring-2 hover:ring-white/20"
                aria-label="Account"
            >
                {auth.user.name.charAt(0).toUpperCase()}
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" className="w-60">
                <DropdownMenuLabel className="font-normal">
                    <p className="truncate text-sm font-medium">
                        {auth.user.name}
                    </p>
                    <p className="truncate text-xs text-muted-foreground">
                        {auth.user.email}
                    </p>
                </DropdownMenuLabel>
                <DropdownMenuSeparator />
                <DropdownMenuItem asChild>
                    <Link href={editProfile()}>
                        <Settings />
                        Settings
                    </Link>
                </DropdownMenuItem>
                <DropdownMenuItem asChild>
                    <Link
                        href={logout()}
                        as="button"
                        onClick={() => router.flushAll()}
                        data-test="logout-button"
                        className="w-full"
                    >
                        <LogOut />
                        Log out
                    </Link>
                </DropdownMenuItem>
            </DropdownMenuContent>
        </DropdownMenu>
    );
}

function MobileNav({ onNavigate }: { onNavigate: () => void }) {
    const activeHref = useActiveHref();

    return (
        <nav
            className="h-full space-y-6 overflow-y-auto bg-rail px-3 py-6 text-rail-foreground"
            aria-label="Main"
        >
            {navGroups.map((group) => (
                <div key={group.title}>
                    <p className="px-3 pb-1.5 text-xs font-medium text-rail-muted">
                        {group.title}
                    </p>
                    <ul className="space-y-0.5">
                        {group.items.map((item) => (
                            <li key={item.title}>
                                <Link
                                    href={item.href}
                                    prefetch
                                    onClick={onNavigate}
                                    className={cn(
                                        'flex items-center gap-3 rounded-md px-3 py-2 text-[0.95rem] font-medium text-rail-foreground/80 transition-colors hover:bg-rail-active hover:text-rail-foreground',
                                        toUrl(item.href) === activeHref &&
                                            'bg-rail-active text-rail-foreground',
                                    )}
                                >
                                    <item.icon className="size-[18px] opacity-80" />
                                    {item.title}
                                </Link>
                            </li>
                        ))}
                    </ul>
                </div>
            ))}
        </nav>
    );
}

export function AppShell({ children }: { children: React.ReactNode }) {
    const { name } = usePage().props;
    const [searchOpen, setSearchOpen] = useState(false);
    const [menuOpen, setMenuOpen] = useState(false);

    useEffect(() => {
        function onKeyDown(event: KeyboardEvent) {
            const target = event.target as HTMLElement | null;
            const typing =
                target?.isContentEditable ||
                ['INPUT', 'TEXTAREA', 'SELECT'].includes(target?.tagName ?? '');

            if (
                (event.metaKey || event.ctrlKey) &&
                event.key.toLowerCase() === 'k'
            ) {
                event.preventDefault();
                setSearchOpen((open) => !open);
            } else if (event.key === '/' && !typing) {
                event.preventDefault();
                setSearchOpen(true);
            }
        }

        window.addEventListener('keydown', onKeyDown);

        return () => window.removeEventListener('keydown', onKeyDown);
    }, []);

    return (
        <div className="min-h-svh bg-background">
            <Sheet open={menuOpen} onOpenChange={setMenuOpen}>
                <SheetContent
                    side="left"
                    className="w-72 border-0 p-0 [&>button]:text-rail-foreground"
                >
                    <SheetTitle className="sr-only">Navigation</SheetTitle>
                    <MobileNav onNavigate={() => setMenuOpen(false)} />
                </SheetContent>
            </Sheet>

            <header className="no-print sticky top-0 z-30 bg-rail text-rail-foreground">
                <div className="mx-auto flex h-16 max-w-[1600px] items-center gap-2 px-4 sm:px-6">
                    <Button
                        variant="ghost"
                        size="icon"
                        className="text-rail-foreground hover:bg-rail-active hover:text-rail-foreground lg:hidden"
                        onClick={() => setMenuOpen(true)}
                        aria-label="Open navigation"
                    >
                        <Menu />
                    </Button>

                    <Link
                        href={dashboard()}
                        className="mr-3 flex shrink-0 items-center gap-2.5"
                    >
                        <LogoMark className="h-7 text-rail-foreground" />
                        <span className="hidden font-display text-lg leading-none font-bold sm:inline">
                            {name}
                        </span>
                    </Link>

                    <HeaderNav />

                    <div className="ml-auto flex items-center gap-2">
                        <button
                            type="button"
                            onClick={() => setSearchOpen(true)}
                            aria-label="Find a card"
                            className="flex h-9 items-center gap-2 rounded-md border border-white/15 bg-rail-active px-2.5 text-sm text-rail-muted transition-colors hover:text-rail-foreground xl:w-56 xl:px-3"
                        >
                            <Search className="size-4" />
                            <span className="hidden flex-1 text-left xl:inline">
                                Find a card…
                            </span>
                            <kbd className="hidden rounded border border-white/15 px-1.5 py-0.5 font-sans text-xs xl:inline">
                                ⌘K
                            </kbd>
                        </button>

                        <Button asChild size="sm" className="h-9">
                            <Link href={collection.create()}>
                                <Plus />
                                <span className="hidden sm:inline">
                                    Add card
                                </span>
                            </Link>
                        </Button>

                        <UserMenu />
                    </div>
                </div>
            </header>

            <main className="mx-auto max-w-[1600px] px-4 py-6 sm:px-6 lg:py-8">
                {children}
            </main>

            <CommandSearch open={searchOpen} onOpenChange={setSearchOpen} />
        </div>
    );
}
