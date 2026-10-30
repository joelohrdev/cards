import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';

export function Field({
    label,
    htmlFor,
    error,
    hint,
    children,
    className,
}: {
    label: React.ReactNode;
    htmlFor?: string;
    error?: string;
    hint?: React.ReactNode;
    children: React.ReactNode;
    className?: string;
}) {
    return (
        <div className={cn('grid content-start gap-1.5', className)}>
            <Label htmlFor={htmlFor}>{label}</Label>
            {children}
            {hint && !error && (
                <p className="text-xs text-muted-foreground">{hint}</p>
            )}
            {error && <p className="text-sm text-destructive">{error}</p>}
        </div>
    );
}

export const controlClass =
    'flex h-9 w-full min-w-0 rounded-md border border-input bg-card px-3 py-1 text-base shadow-xs outline-none transition-[color,box-shadow] placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/30 disabled:opacity-50 md:text-sm aria-invalid:border-destructive';

export function NativeSelect({
    className,
    children,
    ...props
}: React.ComponentProps<'select'>) {
    return (
        <select
            className={cn(controlClass, 'select-chevron pr-9', className)}
            {...props}
        >
            {children}
        </select>
    );
}

export function Textarea({
    className,
    ...props
}: React.ComponentProps<'textarea'>) {
    return (
        <textarea
            className={cn(controlClass, 'min-h-24 py-2', className)}
            {...props}
        />
    );
}

export function MoneyInput({
    className,
    ...props
}: React.ComponentProps<'input'>) {
    return (
        <div className="relative">
            <span className="pointer-events-none absolute inset-y-0 left-3 flex items-center text-sm text-muted-foreground">
                $
            </span>
            <input
                type="text"
                inputMode="decimal"
                className={cn(controlClass, 'pl-6 tabular', className)}
                {...props}
            />
        </div>
    );
}
