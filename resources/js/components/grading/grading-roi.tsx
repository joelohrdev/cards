import { useState } from 'react';
import { MoneyInput } from '@/components/kit/field';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { money } from '@/lib/format';
import { cn } from '@/lib/utils';

type Outcome = { grade: string; value: string; chance: string };

function cents(value: string): number {
    const parsed = Number.parseFloat(value.replace(/[$,\s]/g, ''));

    return Number.isFinite(parsed) ? Math.round(parsed * 100) : 0;
}

function share(value: string): number {
    const parsed = Number.parseFloat(value.replace(/[%\s]/g, ''));

    return Number.isFinite(parsed)
        ? Math.min(100, Math.max(0, parsed)) / 100
        : 0;
}

/**
 * Is it worth grading? Weighs what the card could sell for at each grade against its raw
 * value plus grading costs. Any chance not assigned to a grade is assumed to sell for about
 * the raw value.
 */
export function GradingRoi() {
    const [raw, setRaw] = useState('40');
    const [fee, setFee] = useState('25');
    const [shipping, setShipping] = useState('5');
    const [outcomes, setOutcomes] = useState<Outcome[]>([
        { grade: '10', value: '200', chance: '25' },
        { grade: '9', value: '70', chance: '50' },
        { grade: '8', value: '45', chance: '20' },
    ]);

    const rawValue = cents(raw);
    const cost = cents(fee) + cents(shipping);
    const assigned = outcomes.reduce(
        (sum, outcome) => sum + share(outcome.chance),
        0,
    );
    const leftover = Math.max(0, 1 - assigned);
    const expected =
        outcomes.reduce(
            (sum, outcome) =>
                sum + share(outcome.chance) * cents(outcome.value),
            0,
        ) +
        leftover * rawValue;
    const profit = Math.round(expected - rawValue - cost);
    const tooMuch = assigned > 1.0001;

    function update(index: number, field: keyof Outcome, value: string) {
        setOutcomes((current) =>
            current.map((outcome, position) =>
                position === index ? { ...outcome, [field]: value } : outcome,
            ),
        );
    }

    return (
        <div className="grid gap-5">
            <div className="grid grid-cols-3 gap-3">
                <div className="grid gap-1.5">
                    <Label htmlFor="roi_raw">Raw value</Label>
                    <MoneyInput
                        id="roi_raw"
                        value={raw}
                        onChange={(event) => setRaw(event.target.value)}
                    />
                </div>
                <div className="grid gap-1.5">
                    <Label htmlFor="roi_fee">Grading fee</Label>
                    <MoneyInput
                        id="roi_fee"
                        value={fee}
                        onChange={(event) => setFee(event.target.value)}
                    />
                </div>
                <div className="grid gap-1.5">
                    <Label htmlFor="roi_shipping">Shipping per card</Label>
                    <MoneyInput
                        id="roi_shipping"
                        value={shipping}
                        onChange={(event) => setShipping(event.target.value)}
                    />
                </div>
            </div>

            <table className="w-full text-sm">
                <thead className="text-left text-xs text-muted-foreground">
                    <tr>
                        <th className="pb-2 font-medium">Grade</th>
                        <th className="pb-2 font-medium">Sells for</th>
                        <th className="pb-2 font-medium">Chance</th>
                    </tr>
                </thead>
                <tbody>
                    {outcomes.map((outcome, index) => (
                        <tr key={index}>
                            <td className="w-20 py-1 pr-2">
                                <Input
                                    aria-label="Grade"
                                    value={outcome.grade}
                                    onChange={(event) =>
                                        update(
                                            index,
                                            'grade',
                                            event.target.value,
                                        )
                                    }
                                />
                            </td>
                            <td className="py-1 pr-2">
                                <MoneyInput
                                    aria-label={`Value at grade ${outcome.grade}`}
                                    value={outcome.value}
                                    onChange={(event) =>
                                        update(
                                            index,
                                            'value',
                                            event.target.value,
                                        )
                                    }
                                />
                            </td>
                            <td className="w-24 py-1">
                                <div className="relative">
                                    <Input
                                        aria-label={`Chance of grade ${outcome.grade}`}
                                        inputMode="decimal"
                                        className="pr-7 tabular"
                                        value={outcome.chance}
                                        onChange={(event) =>
                                            update(
                                                index,
                                                'chance',
                                                event.target.value,
                                            )
                                        }
                                    />
                                    <span className="pointer-events-none absolute inset-y-0 right-3 flex items-center text-sm text-muted-foreground">
                                        %
                                    </span>
                                </div>
                            </td>
                        </tr>
                    ))}
                </tbody>
            </table>

            {tooMuch ? (
                <p className="text-sm text-destructive">
                    The chances add up to more than 100%. Lower one of them.
                </p>
            ) : (
                leftover > 0 && (
                    <p className="text-xs text-muted-foreground">
                        The other {Math.round(leftover * 100)}% is assumed to
                        sell for about the raw value.
                    </p>
                )
            )}

            <div className="grid grid-cols-2 gap-4 rounded-md bg-muted p-4">
                <div>
                    <p className="text-sm text-muted-foreground">
                        Expected value graded
                    </p>
                    <p className="font-display text-2xl font-bold tabular">
                        {money(Math.round(expected))}
                    </p>
                </div>
                <div>
                    <p className="text-sm text-muted-foreground">
                        Expected profit vs. selling raw
                    </p>
                    <p
                        className={cn(
                            'font-display text-2xl font-bold tabular',
                            !tooMuch &&
                                (profit >= 0 ? 'text-gain' : 'text-loss'),
                        )}
                    >
                        {tooMuch
                            ? '—'
                            : `${profit >= 0 ? '+' : '−'}${money(Math.abs(profit))}`}
                    </p>
                </div>
                <p className="col-span-2 text-xs text-muted-foreground">
                    After {money(cost)} in grading costs.{' '}
                    {profit >= 0 && !tooMuch
                        ? 'Grading looks worth it.'
                        : 'Selling raw may be the better bet.'}
                </p>
            </div>
        </div>
    );
}
