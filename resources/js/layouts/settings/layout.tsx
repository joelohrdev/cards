import { Link, usePage } from '@inertiajs/react';
import type { PropsWithChildren } from 'react';
import { PageHeader } from '@/components/kit/page-header';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { cn } from '@/lib/utils';
import { edit as editAppearance } from '@/routes/appearance';
import { edit } from '@/routes/profile';
import { edit as editSecurity } from '@/routes/security';
import users from '@/routes/users';

const sections = [
    { title: 'Profile', href: edit() },
    { title: 'Security', href: editSecurity() },
    { title: 'Appearance', href: editAppearance() },
];

export default function SettingsLayout({ children }: PropsWithChildren) {
    const { isCurrentOrParentUrl } = useCurrentUrl();
    const { auth } = usePage().props;
    const visibleSections =
        auth.user.role === 'admin'
            ? [...sections, { title: 'Users', href: users.index() }]
            : sections;

    return (
        <>
            <PageHeader title="Settings" />

            <div className="flex flex-col gap-6 lg:flex-row lg:gap-10">
                <nav
                    className="flex gap-1 overflow-x-auto lg:w-48 lg:flex-col"
                    aria-label="Settings"
                >
                    {visibleSections.map((section) => (
                        <Link
                            key={section.title}
                            href={section.href}
                            className={cn(
                                'rounded-md px-3 py-2 text-sm font-medium whitespace-nowrap text-muted-foreground hover:bg-accent hover:text-foreground',
                                isCurrentOrParentUrl(section.href) &&
                                    'bg-card text-foreground shadow-xs ring-1 ring-border',
                            )}
                        >
                            {section.title}
                        </Link>
                    ))}
                </nav>

                <div className="max-w-xl flex-1 space-y-10 rounded-lg border bg-card p-6">
                    {children}
                </div>
            </div>
        </>
    );
}
