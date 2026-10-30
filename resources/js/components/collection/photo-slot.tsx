import { ImagePlus, X } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { cn } from '@/lib/utils';

/**
 * A card-shaped picker for one photo (front or back) with a preview of the chosen file.
 */
export function PhotoSlot({
    label,
    file,
    onChange,
    error,
}: {
    label: string;
    file: File | null;
    onChange: (file: File | null) => void;
    error?: string;
}) {
    const input = useRef<HTMLInputElement>(null);
    const [preview, setPreview] = useState<string | null>(null);

    useEffect(() => {
        if (!file) {
            setPreview(null);

            return;
        }

        const url = URL.createObjectURL(file);
        setPreview(url);

        return () => URL.revokeObjectURL(url);
    }, [file]);

    return (
        <div>
            <div
                className={cn(
                    'relative aspect-[5/7] w-full overflow-hidden rounded-md border-2 border-dashed bg-muted/40',
                    preview && 'border-solid',
                    error && 'border-destructive',
                )}
            >
                <button
                    type="button"
                    onClick={() => input.current?.click()}
                    className="flex size-full flex-col items-center justify-center gap-2 text-sm text-muted-foreground hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                >
                    {preview ? (
                        <img
                            src={preview}
                            alt={`${label} photo`}
                            className="size-full object-cover"
                        />
                    ) : (
                        <>
                            <ImagePlus className="size-6" />
                            Add {label.toLowerCase()}
                        </>
                    )}
                </button>
                {file && (
                    <button
                        type="button"
                        onClick={() => onChange(null)}
                        aria-label={`Remove ${label.toLowerCase()} photo`}
                        className="absolute top-1.5 right-1.5 rounded-full bg-background/90 p-1 shadow hover:bg-background"
                    >
                        <X className="size-4" />
                    </button>
                )}
            </div>
            <p className="mt-1.5 text-center text-sm font-medium">{label}</p>
            {error && (
                <p className="mt-1 text-center text-sm text-destructive">
                    {error}
                </p>
            )}
            <input
                ref={input}
                type="file"
                accept="image/jpeg,image/png,image/webp"
                className="hidden"
                onChange={(event) => {
                    onChange(event.target.files?.[0] ?? null);
                    event.target.value = '';
                }}
            />
        </div>
    );
}
