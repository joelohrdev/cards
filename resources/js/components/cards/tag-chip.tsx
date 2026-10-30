import { X } from 'lucide-react';
import { cn } from '@/lib/utils';

const tones: Record<string, string> = {
    slate: 'bg-slate-500/12 text-slate-700 dark:text-slate-300',
    red: 'bg-red-500/12 text-red-700 dark:text-red-300',
    orange: 'bg-orange-500/12 text-orange-700 dark:text-orange-300',
    amber: 'bg-amber-500/15 text-amber-800 dark:text-amber-300',
    green: 'bg-green-500/12 text-green-700 dark:text-green-300',
    teal: 'bg-teal-500/12 text-teal-700 dark:text-teal-300',
    blue: 'bg-blue-500/12 text-blue-700 dark:text-blue-300',
    indigo: 'bg-indigo-500/12 text-indigo-700 dark:text-indigo-300',
    purple: 'bg-purple-500/12 text-purple-700 dark:text-purple-300',
    pink: 'bg-pink-500/12 text-pink-700 dark:text-pink-300',
};

export const tagDot: Record<string, string> = {
    slate: 'bg-slate-500',
    red: 'bg-red-500',
    orange: 'bg-orange-500',
    amber: 'bg-amber-500',
    green: 'bg-green-500',
    teal: 'bg-teal-500',
    blue: 'bg-blue-500',
    indigo: 'bg-indigo-500',
    purple: 'bg-purple-500',
    pink: 'bg-pink-500',
};

export function TagChip({
    name,
    color = 'slate',
    onRemove,
    className,
}: {
    name: string;
    color?: string;
    onRemove?: () => void;
    className?: string;
}) {
    return (
        <span
            className={cn(
                'inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-medium',
                tones[color] ?? tones.slate,
                className,
            )}
        >
            {name}
            {onRemove && (
                <button
                    type="button"
                    onClick={onRemove}
                    className="-mr-1 rounded-full p-0.5 hover:bg-black/10"
                    aria-label={`Remove ${name}`}
                >
                    <X className="size-3" />
                </button>
            )}
        </span>
    );
}
