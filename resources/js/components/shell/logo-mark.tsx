import { cn } from '@/lib/utils';

/**
 * A card in a top loader: the app mark.
 */
export function LogoMark({ className }: { className?: string }) {
    return (
        <svg
            viewBox="0 0 28 36"
            aria-hidden="true"
            className={cn('h-8 w-auto', className)}
        >
            <rect
                x="1"
                y="1"
                width="26"
                height="34"
                rx="3"
                className="fill-none stroke-current opacity-50"
                strokeWidth="1.5"
            />
            <rect
                x="5"
                y="5"
                width="18"
                height="26"
                rx="1.5"
                className="fill-current"
            />
            <rect
                x="8"
                y="22"
                width="12"
                height="5"
                rx="0.5"
                className="fill-slab"
            />
        </svg>
    );
}
