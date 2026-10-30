import { usePage } from '@inertiajs/react';
import { LogoMark } from '@/components/shell/logo-mark';

/**
 * Sign-in screens: a binder-spine panel with a fanned stack of cards beside the form.
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
        <div className="grid min-h-svh bg-background lg:grid-cols-[minmax(0,1fr)_minmax(0,1.1fr)]">
            <aside className="relative hidden overflow-hidden bg-rail p-10 text-rail-foreground lg:flex lg:flex-col">
                <div className="flex items-center gap-3">
                    <LogoMark className="h-10" />
                    <span className="font-display text-2xl font-bold">
                        {name}
                    </span>
                </div>

                <div
                    className="relative mx-auto my-auto h-80 w-72"
                    aria-hidden="true"
                >
                    {[
                        { rotate: -14, x: -70, tone: 'bg-[#2f6fb3]' },
                        { rotate: -4, x: -20, tone: 'bg-[#c9972e]' },
                        { rotate: 8, x: 40, tone: 'bg-[#17604a]' },
                    ].map((card, index) => (
                        <div
                            key={index}
                            className="absolute top-6 left-1/2 aspect-[5/7] w-44 rounded-xl border-4 border-white/90 shadow-2xl"
                            style={{
                                transform: `translateX(calc(-50% + ${card.x}px)) rotate(${card.rotate}deg)`,
                            }}
                        >
                            <div
                                className={`h-full w-full rounded-lg ${card.tone} p-3`}
                            >
                                <div className="h-3/4 rounded bg-white/15" />
                                <div className="mt-3 h-2 w-3/4 rounded bg-white/40" />
                                <div className="mt-1.5 h-2 w-1/2 rounded bg-white/25" />
                            </div>
                        </div>
                    ))}
                    <div className="absolute top-0 right-0 flex items-stretch overflow-hidden rounded-sm border-2 border-slab bg-white text-sm font-bold shadow-lg">
                        <span className="bg-slab px-2 py-1 text-white">
                            PSA
                        </span>
                        <span className="px-2 py-1 font-display text-lg leading-none text-[#15213a]">
                            10
                        </span>
                    </div>
                </div>

                <p className="max-w-sm font-display text-3xl leading-tight font-semibold">
                    Every card you own, where it lives, and what it’s worth.
                </p>
            </aside>

            <main className="flex items-center justify-center p-6 sm:p-10">
                <div className="w-full max-w-sm">
                    <div className="mb-8 flex items-center gap-3 lg:hidden">
                        <LogoMark className="h-9" />
                        <span className="font-display text-2xl font-bold">
                            {name}
                        </span>
                    </div>
                    <h1 className="font-display text-3xl font-bold">{title}</h1>
                    {description && (
                        <p className="mt-1 mb-8 text-muted-foreground">
                            {description}
                        </p>
                    )}
                    {children}
                </div>
            </main>
        </div>
    );
}
