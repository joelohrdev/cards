import { usePage } from '@inertiajs/react';
import { LogoMark } from '@/components/shell/logo-mark';

/**
 * Sign-in screens: the form, centered, under the app mark.
 */
export default function AuthLayout({
    title = '',
    description = '',
    children,
}: {
    title?: string;
    description?: string;
    children: React.ReactNode;
}) {
    const { name } = usePage().props;

    return (
        <main className="flex min-h-svh items-center justify-center bg-background p-6 sm:p-10">
            <div className="w-full max-w-sm">
                <div className="mb-8 flex items-center gap-3">
                    <LogoMark className="size-9" />
                    <span className="font-display text-2xl font-bold">
                        {name}
                    </span>
                </div>
                <h1 className="mb-6 font-display text-3xl font-bold">
                    {title}
                </h1>
                {description && (
                    <p className="-mt-5 mb-8 text-muted-foreground">
                        {description}
                    </p>
                )}
                {children}
            </div>
        </main>
    );
}
