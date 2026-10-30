const currency = new Intl.NumberFormat('en-US', {
    style: 'currency',
    currency: 'USD',
});

const compactCurrency = new Intl.NumberFormat('en-US', {
    style: 'currency',
    currency: 'USD',
    notation: 'compact',
    maximumFractionDigits: 1,
});

/**
 * Format integer cents as dollars, e.g. 12345 → "$123.45".
 */
export function money(
    cents: number | null | undefined,
    fallback = '—',
): string {
    if (cents === null || cents === undefined) {
        return fallback;
    }

    return currency.format(cents / 100);
}

/**
 * Short dollars for headline numbers, e.g. 1234500 → "$12.3K".
 */
export function compactMoney(cents: number | null | undefined): string {
    if (cents === null || cents === undefined) {
        return '—';
    }

    return Math.abs(cents) >= 1_000_000
        ? compactCurrency.format(cents / 100)
        : currency.format(Math.round(cents / 100));
}

/**
 * Cents to an editable dollar string for form inputs.
 */
export function dollarsInput(cents: number | null | undefined): string {
    return cents === null || cents === undefined
        ? ''
        : (cents / 100).toFixed(2);
}

export function count(
    value: number,
    noun: string,
    plural = `${noun}s`,
): string {
    return `${value.toLocaleString()} ${value === 1 ? noun : plural}`;
}

export function shortDate(value: string | null | undefined): string {
    if (!value) {
        return '—';
    }

    const date = new Date(value.length === 10 ? `${value}T12:00:00` : value);

    return date.toLocaleDateString('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
    });
}

export function percent(part: number, whole: number): number {
    return whole > 0 ? Math.round((part / whole) * 100) : 0;
}
