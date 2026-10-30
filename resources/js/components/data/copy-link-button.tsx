import { Check, Copy } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import { Button } from '@/components/ui/button';

/**
 * Copies a URL to the clipboard and confirms it in place.
 */
export function CopyLinkButton({
    url,
    label = 'Copy link',
}: {
    url: string;
    label?: string;
}) {
    const [copied, setCopied] = useState(false);

    async function copy() {
        try {
            await navigator.clipboard.writeText(url);
            setCopied(true);
            window.setTimeout(() => setCopied(false), 2000);
        } catch {
            toast.error(
                'Your browser blocked copying. Select the link and copy it by hand.',
            );
        }
    }

    return (
        <Button type="button" variant="outline" size="sm" onClick={copy}>
            {copied ? <Check /> : <Copy />}
            {copied ? 'Copied' : label}
        </Button>
    );
}
