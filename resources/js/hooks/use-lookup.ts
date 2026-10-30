import { useEffect, useRef, useState } from 'react';

/**
 * Debounced JSON lookups against same-origin endpoints (card picker, global search).
 */
export function useLookup<T>(
    url: string | null,
    delay = 200,
): { data: T | null; loading: boolean } {
    const [data, setData] = useState<T | null>(null);
    const [loading, setLoading] = useState(false);
    const controller = useRef<AbortController | null>(null);

    useEffect(() => {
        if (url === null) {
            setData(null);
            setLoading(false);

            return;
        }

        const timer = window.setTimeout(() => {
            controller.current?.abort();
            controller.current = new AbortController();
            setLoading(true);

            fetch(url, {
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
                signal: controller.current.signal,
            })
                .then((response) => response.json() as Promise<T>)
                .then((json) => {
                    setData(json);
                    setLoading(false);
                })
                .catch((error: unknown) => {
                    if (
                        !(
                            error instanceof DOMException &&
                            error.name === 'AbortError'
                        )
                    ) {
                        setLoading(false);
                    }
                });
        }, delay);

        return () => window.clearTimeout(timer);
    }, [url, delay]);

    return { data, loading };
}
