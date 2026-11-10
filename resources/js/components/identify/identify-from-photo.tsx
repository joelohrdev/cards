import { ScanSearch } from 'lucide-react';
import { useRef, useState } from 'react';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { useLookup } from '@/hooks/use-lookup';
import { shrinkPhoto } from '@/lib/shrink-photo';
import { cn } from '@/lib/utils';
import identify from '@/routes/identify';
import type { PhotoSuggestion } from '@/types';

/**
 * Longest edge sent to the vision model. The scaled-down photo is also what the
 * caller gets back to save as the card's front.
 */
const MAX_EDGE = 1568;

function readCookie(name: string): string | null {
    const match = document.cookie
        .split('; ')
        .find((entry) => entry.startsWith(`${name}=`));

    return match ? decodeURIComponent(match.slice(name.length + 1)) : null;
}

/**
 * "Identify from photo": opens the camera or file picker, asks the vision model what card it
 * is, and hands back a suggestion with likely catalog matches.
 */
export function IdentifyFromPhoto({
    onResult,
}: {
    onResult: (suggestion: PhotoSuggestion, photo: File) => void;
}) {
    const input = useRef<HTMLInputElement>(null);
    const [working, setWorking] = useState(false);
    const [message, setMessage] = useState<{
        tone: 'ok' | 'error';
        text: string;
    } | null>(null);
    const { data: status } = useLookup<{ configured: boolean }>(
        identify.photo.show.url(),
        0,
    );
    const notConfigured = status !== null && !status.configured;

    function choosePhoto() {
        if (notConfigured) {
            setMessage({
                tone: 'error',
                text: 'Photo identification isn’t set up yet. Add an Anthropic API key as ANTHROPIC_API_KEY in the .env file to turn it on.',
            });

            return;
        }

        input.current?.click();
    }

    async function upload(file: File | undefined) {
        if (!file) {
            return;
        }

        setWorking(true);
        setMessage(null);

        try {
            const photo = await shrinkPhoto(file, MAX_EDGE);
            const body = new FormData();
            body.append('photo', photo, photo.name);

            const response = await fetch(identify.photo.store.url(), {
                method: 'POST',
                body,
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-XSRF-TOKEN': readCookie('XSRF-TOKEN') ?? '',
                },
            });
            const json = (await response.json().catch(() => ({}))) as {
                suggestion?: PhotoSuggestion;
                message?: string;
                errors?: Record<string, string[]>;
            };

            if (response.ok && json.suggestion) {
                onResult(json.suggestion, photo);
                setMessage({
                    tone: 'ok',
                    text:
                        json.suggestion.matches.length > 0
                            ? 'Filled in what we could read. Check the details before saving.'
                            : 'Filled in what we could read, but found no matching card in the catalog. Check the details before saving.',
                });

                return;
            }

            if (response.status === 429) {
                setMessage({
                    tone: 'error',
                    text: 'That’s a lot of photos in a row. Wait a minute, then try again.',
                });

                return;
            }

            const firstError = json.errors
                ? Object.values(json.errors)[0]?.[0]
                : undefined;
            setMessage({
                tone: 'error',
                text:
                    firstError ??
                    json.message ??
                    'Couldn’t identify that photo. Try again, or enter the card by hand.',
            });
        } catch {
            setMessage({
                tone: 'error',
                text: 'Couldn’t reach the server. Check your connection and try again.',
            });
        } finally {
            setWorking(false);

            if (input.current) {
                input.current.value = '';
            }
        }
    }

    return (
        <div className="grid gap-1.5">
            <Button
                type="button"
                variant="outline"
                onClick={choosePhoto}
                disabled={working}
            >
                {working ? <Spinner /> : <ScanSearch />}
                {working ? 'Reading the card…' : 'Identify from photo'}
            </Button>
            <input
                ref={input}
                type="file"
                accept="image/*"
                capture="environment"
                className="hidden"
                onChange={(event) => upload(event.target.files?.[0])}
            />
            {message && (
                <p
                    className={cn(
                        'text-xs',
                        message.tone === 'error'
                            ? 'text-destructive'
                            : 'text-muted-foreground',
                    )}
                    role="status"
                >
                    {message.text}
                </p>
            )}
        </div>
    );
}
