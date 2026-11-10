import { cn } from '@/lib/utils';

/**
 * Two fanned cards on a rounded green tile: the app mark. It matches
 * public/favicon.svg and the iPad home-screen icon.
 */
export function LogoMark({ className }: { className?: string }) {
    return (
        <svg
            viewBox="0 0 32 32"
            aria-hidden="true"
            className={cn('size-8 shrink-0', className)}
        >
            <rect width="32" height="32" rx="8" className="fill-[#3fa27f]" />
            <rect
                x="9"
                y="6"
                width="14"
                height="20"
                rx="2"
                fill="none"
                stroke="#fff"
                strokeOpacity="0.55"
                strokeWidth="2"
                transform="rotate(-12 16 16)"
            />
            <rect
                x="9"
                y="6"
                width="14"
                height="20"
                rx="2"
                fill="#fff"
                transform="rotate(8 16 16)"
            />
        </svg>
    );
}
