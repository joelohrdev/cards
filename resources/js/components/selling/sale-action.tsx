import { Form, router } from '@inertiajs/react';
import { HandCoins } from 'lucide-react';
import { useState } from 'react';
import {
    Field,
    MoneyInput,
    NativeSelect,
    Textarea,
} from '@/components/kit/field';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { money } from '@/lib/format';
import { cn } from '@/lib/utils';
import sales from '@/routes/sales';
import trades from '@/routes/trades';
import type { CollectionItem, FormOptions } from '@/types';

function toCents(value: string): number {
    const parsed = Number.parseFloat(value.replace(/[$,\s]/g, ''));

    return Number.isFinite(parsed) ? Math.round(parsed * 100) : 0;
}

function today(): string {
    const now = new Date();

    return new Date(now.getTime() - now.getTimezoneOffset() * 60_000)
        .toISOString()
        .slice(0, 10);
}

/**
 * "Record sale" dialog for a card still in hand. Saving marks the card sold.
 */
export function SaleAction({
    item,
    options,
}: {
    item: CollectionItem;
    options: FormOptions;
}) {
    const [open, setOpen] = useState(false);
    const [price, setPrice] = useState('');
    const [fees, setFees] = useState('');
    const [shipping, setShipping] = useState('');
    const [trading, setTrading] = useState(false);

    if (!['owned', 'listed'].includes(item.status)) {
        return null;
    }

    const net = toCents(price) - toCents(fees) - toCents(shipping);
    const profit =
        item.purchase_price === null ? null : net - item.purchase_price;

    function markTraded() {
        setTrading(true);
        router.post(
            trades.store.url(),
            { collection_item_id: item.id },
            {
                preserveScroll: true,
                onSuccess: () => setOpen(false),
                onFinish: () => setTrading(false),
            },
        );
    }

    return (
        <>
            <Button variant="outline" onClick={() => setOpen(true)}>
                <HandCoins /> Record sale
            </Button>

            <Dialog open={open} onOpenChange={setOpen}>
                <DialogContent className="sm:max-w-lg">
                    <DialogHeader>
                        <DialogTitle>Record a sale</DialogTitle>
                        <DialogDescription>
                            {item.title} will be marked sold and added to your
                            sales ledger.
                        </DialogDescription>
                    </DialogHeader>

                    <Form
                        action={sales.store.url()}
                        method="post"
                        options={{ preserveScroll: true }}
                        onSuccess={() => setOpen(false)}
                        className="grid gap-4 sm:grid-cols-2"
                    >
                        {({ errors, processing }) => (
                            <>
                                <input
                                    type="hidden"
                                    name="collection_item_id"
                                    value={item.id}
                                />
                                {errors.collection_item_id && (
                                    <p className="text-sm text-destructive sm:col-span-2">
                                        {errors.collection_item_id}
                                    </p>
                                )}
                                <Field
                                    label="Sold for"
                                    htmlFor="sale_price"
                                    error={errors.sale_price}
                                >
                                    <MoneyInput
                                        id="sale_price"
                                        name="sale_price"
                                        required
                                        placeholder="0.00"
                                        value={price}
                                        onChange={(event) =>
                                            setPrice(event.target.value)
                                        }
                                    />
                                </Field>
                                <Field
                                    label="Date sold"
                                    htmlFor="sold_on"
                                    error={errors.sold_on}
                                >
                                    <Input
                                        id="sold_on"
                                        type="date"
                                        name="sold_on"
                                        required
                                        defaultValue={today()}
                                        max={today()}
                                    />
                                </Field>
                                <Field
                                    label="Where"
                                    htmlFor="platform"
                                    error={errors.platform}
                                >
                                    <NativeSelect
                                        id="platform"
                                        name="platform"
                                        defaultValue="ebay"
                                    >
                                        {options.platforms.map((platform) => (
                                            <option
                                                key={platform.value}
                                                value={platform.value}
                                            >
                                                {platform.label}
                                            </option>
                                        ))}
                                    </NativeSelect>
                                </Field>
                                <Field
                                    label="Buyer"
                                    htmlFor="buyer"
                                    error={errors.buyer}
                                    hint="Optional"
                                >
                                    <Input
                                        id="buyer"
                                        name="buyer"
                                        autoComplete="off"
                                    />
                                </Field>
                                <Field
                                    label="Fees"
                                    htmlFor="fees"
                                    error={errors.fees}
                                    hint="Platform and payment fees"
                                >
                                    <MoneyInput
                                        id="fees"
                                        name="fees"
                                        placeholder="0.00"
                                        value={fees}
                                        onChange={(event) =>
                                            setFees(event.target.value)
                                        }
                                    />
                                </Field>
                                <Field
                                    label="Shipping you paid"
                                    htmlFor="shipping_cost"
                                    error={errors.shipping_cost}
                                >
                                    <MoneyInput
                                        id="shipping_cost"
                                        name="shipping_cost"
                                        placeholder="0.00"
                                        value={shipping}
                                        onChange={(event) =>
                                            setShipping(event.target.value)
                                        }
                                    />
                                </Field>
                                <Field
                                    label="Notes"
                                    htmlFor="sale_notes"
                                    error={errors.notes}
                                    className="sm:col-span-2"
                                >
                                    <Textarea
                                        id="sale_notes"
                                        name="notes"
                                        className="min-h-16"
                                    />
                                </Field>

                                <div className="flex flex-wrap items-baseline justify-between gap-2 rounded-md bg-muted px-3 py-2 text-sm sm:col-span-2">
                                    <span>
                                        You net{' '}
                                        <span className="font-display text-lg font-semibold tabular">
                                            {money(net)}
                                        </span>
                                    </span>
                                    {profit !== null && price !== '' && (
                                        <span
                                            className={cn(
                                                'tabular',
                                                profit >= 0
                                                    ? 'text-gain'
                                                    : 'text-loss',
                                            )}
                                        >
                                            {profit >= 0 ? '+' : '−'}
                                            {money(Math.abs(profit))} vs.{' '}
                                            {money(item.purchase_price)} paid
                                        </span>
                                    )}
                                </div>

                                <DialogFooter className="gap-2 sm:col-span-2 sm:justify-between">
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        disabled={trading}
                                        onClick={markTraded}
                                    >
                                        Mark as traded instead
                                    </Button>
                                    <Button type="submit" disabled={processing}>
                                        {processing ? 'Saving…' : 'Record sale'}
                                    </Button>
                                </DialogFooter>
                            </>
                        )}
                    </Form>
                </DialogContent>
            </Dialog>
        </>
    );
}
