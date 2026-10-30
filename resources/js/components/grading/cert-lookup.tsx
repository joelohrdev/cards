import { Search } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { cn } from '@/lib/utils';
import certs from '@/routes/certs';
import type { CertLookupResult } from '@/types';

/**
 * A small "Look up" button that fetches a slab's details from the grader by cert number
 * and hands them back through onResult. PSA only for now.
 */
export function CertLookup({
    grader,
    certNumber,
    onResult,
}: {
    grader: string;
    certNumber: string;
    onResult: (result: CertLookupResult) => void;
}) {
    const [loading, setLoading] = useState(false);
    const [message, setMessage] = useState<{
        tone: 'ok' | 'error';
        text: string;
    } | null>(null);
    const cert = certNumber.trim();
    const canLookUp = grader === 'PSA' && cert !== '';

    async function lookUp() {
        setLoading(true);
        setMessage(null);

        try {
            const response = await fetch(
                certs.lookup.url({ query: { grader, cert_number: cert } }),
                {
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    credentials: 'same-origin',
                },
            );
            const body = (await response.json().catch(() => ({}))) as {
                result?: CertLookupResult;
                message?: string;
                errors?: Record<string, string[]>;
            };

            if (response.ok && body.result) {
                const result = body.result;
                onResult(result);
                setMessage({
                    tone: 'ok',
                    text: `Found ${[result.year, result.brand, result.card_number && `#${result.card_number}`, result.player].filter(Boolean).join(' ')}${result.grade ? ` · PSA ${result.grade}` : ''}.`,
                });

                return;
            }

            const firstError = body.errors
                ? Object.values(body.errors)[0]?.[0]
                : undefined;
            setMessage({
                tone: 'error',
                text:
                    firstError ??
                    body.message ??
                    'The lookup didn’t work. Enter the details from the label by hand.',
            });
        } catch {
            setMessage({
                tone: 'error',
                text: 'Couldn’t reach the server. Check your connection and try again.',
            });
        } finally {
            setLoading(false);
        }
    }

    return (
        <div className="grid gap-1">
            <Button
                type="button"
                variant="outline"
                size="sm"
                disabled={!canLookUp || loading}
                onClick={lookUp}
                title={
                    grader === 'PSA'
                        ? 'Fill in the card from PSA’s records'
                        : 'Cert lookup works for PSA slabs'
                }
            >
                {loading ? <Spinner /> : <Search />} Look up
            </Button>
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
