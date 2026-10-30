import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { CheckCircle2, Circle, ExternalLink } from 'lucide-react';
import { Field, NativeSelect } from '@/components/kit/field';
import { PageHeader } from '@/components/kit/page-header';
import { Panel } from '@/components/kit/panel';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { shortDate } from '@/lib/format';
import { cn } from '@/lib/utils';
import ebay from '@/routes/ebay';

type Policy = { id: string; name: string };

type Props = {
    configured: boolean;
    environment: string;
    marketplace: string;
    envVars: { name: string; description: string }[];
    callbackUrl: string;
    deletionEndpointUrl: string;
    account: {
        username: string | null;
        connected_at: string | null;
        fulfillment_policy_id: string | null;
        payment_policy_id: string | null;
        return_policy_id: string | null;
        merchant_location_key: string | null;
        ready: boolean;
        orders_synced_at: string | null;
    } | null;
    policies: {
        fulfillment: Policy[];
        payment: Policy[];
        return: Policy[];
    } | null;
    policiesError: string | null;
    sellerHubUrl: string;
};

export default function EbaySettings(props: Props) {
    const { configured, account } = props;

    return (
        <>
            <Head title="eBay settings" />

            <PageHeader
                eyebrow={
                    <Link
                        href={ebay.listings.index()}
                        className="hover:text-foreground"
                    >
                        eBay listings
                    </Link>
                }
                title="eBay settings"
                description="Connect your eBay seller account and choose how your cards ship. Every listing uses these settings."
            />

            {!configured ? (
                <NotConfigured {...props} />
            ) : (
                <div className="grid max-w-3xl gap-6">
                    <Checklist account={account} />
                    <Connection {...props} />
                    {account && <Policies {...props} />}
                    {account && (
                        <Location
                            hasLocation={Boolean(account.merchant_location_key)}
                        />
                    )}
                </div>
            )}
        </>
    );
}

function NotConfigured({ envVars, callbackUrl, deletionEndpointUrl }: Props) {
    return (
        <Panel title="Set up your eBay developer keys" className="max-w-3xl">
            <div className="space-y-4 text-sm">
                <p className="text-muted-foreground">
                    The eBay integration needs API keys from the eBay developer
                    program. Add these to your{' '}
                    <code className="rounded bg-muted px-1">.env</code> file,
                    then reload this page.
                </p>
                <dl className="divide-y rounded-md border">
                    {envVars.map((envVar) => (
                        <div
                            key={envVar.name}
                            className="grid gap-1 px-3 py-2 sm:grid-cols-[18rem_1fr]"
                        >
                            <dt className="font-mono text-xs">{envVar.name}</dt>
                            <dd className="text-muted-foreground">
                                {envVar.description}
                            </dd>
                        </div>
                    ))}
                </dl>
                <ol className="list-decimal space-y-1.5 pl-5 text-muted-foreground">
                    <li>
                        Create a keyset at{' '}
                        <a
                            href="https://developer.ebay.com/my/keys"
                            target="_blank"
                            rel="noreferrer"
                            className="text-primary hover:underline"
                        >
                            developer.ebay.com/my/keys
                        </a>
                        .
                    </li>
                    <li>
                        Under User Tokens, add an eBay Redirect URL (RuName).
                        Set its auth accepted and declined URLs to{' '}
                        <CopyableUrl url={callbackUrl} />. Its name is your{' '}
                        <code className="rounded bg-muted px-1">
                            EBAY_RU_NAME
                        </code>
                        .
                    </li>
                    <li>
                        For production keys, subscribe to marketplace account
                        deletion notifications with the endpoint{' '}
                        <CopyableUrl url={deletionEndpointUrl} /> and the
                        verification token you put in{' '}
                        <code className="rounded bg-muted px-1">
                            EBAY_DELETION_VERIFICATION_TOKEN
                        </code>
                        .
                    </li>
                </ol>
            </div>
        </Panel>
    );
}

function CopyableUrl({ url }: { url: string }) {
    return (
        <code className="rounded bg-muted px-1 break-all text-foreground">
            {url}
        </code>
    );
}

function Checklist({ account }: { account: Props['account'] }) {
    const steps = [
        { label: 'Connect your eBay account', done: Boolean(account) },
        {
            label: 'Choose shipping, payment and return policies',
            done: Boolean(
                account?.fulfillment_policy_id &&
                account.payment_policy_id &&
                account.return_policy_id,
            ),
        },
        {
            label: 'Set where your cards ship from',
            done: Boolean(account?.merchant_location_key),
        },
    ];

    return (
        <ol className="grid gap-2 sm:grid-cols-3">
            {steps.map((step, index) => (
                <li
                    key={step.label}
                    className={cn(
                        'flex items-start gap-2 rounded-lg border bg-card p-3 text-sm',
                        step.done && 'text-muted-foreground',
                    )}
                >
                    {step.done ? (
                        <CheckCircle2 className="mt-0.5 size-4 shrink-0 text-gain" />
                    ) : (
                        <Circle className="mt-0.5 size-4 shrink-0" />
                    )}
                    <span>
                        <span className="font-display font-semibold tabular">
                            {index + 1}.
                        </span>{' '}
                        {step.label}
                    </span>
                </li>
            ))}
        </ol>
    );
}

function Connection({
    account,
    environment,
    marketplace,
    sellerHubUrl,
}: Props) {
    const disconnect = useForm({});

    return (
        <Panel title="eBay account">
            {account ? (
                <div className="flex flex-wrap items-center justify-between gap-4">
                    <div className="text-sm">
                        <p>
                            Connected as{' '}
                            <span className="font-medium">
                                {account.username ?? 'your eBay account'}
                            </span>
                        </p>
                        <p className="text-muted-foreground">
                            {environment === 'production'
                                ? 'Live eBay'
                                : 'eBay sandbox'}{' '}
                            · {marketplace}
                            {account.orders_synced_at &&
                                ` · Orders checked ${shortDate(account.orders_synced_at)}`}
                        </p>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        <Button asChild variant="outline">
                            <a
                                href={sellerHubUrl}
                                target="_blank"
                                rel="noreferrer"
                            >
                                <ExternalLink /> Open Seller Hub
                            </a>
                        </Button>
                        <Button
                            variant="outline"
                            disabled={disconnect.processing}
                            onClick={() =>
                                disconnect.delete(ebay.disconnect.url(), {
                                    preserveScroll: true,
                                })
                            }
                        >
                            Disconnect eBay
                        </Button>
                    </div>
                </div>
            ) : (
                <div className="flex flex-wrap items-center justify-between gap-4">
                    <p className="max-w-md text-sm text-muted-foreground">
                        You’ll sign in on eBay and allow this app to create
                        listings and read your orders. Your eBay password is
                        never shared with this app.
                    </p>
                    <Button asChild>
                        <a href={ebay.connect.url()}>Connect eBay</a>
                    </Button>
                </div>
            )}
        </Panel>
    );
}

function Policies({ account, policies, policiesError, sellerHubUrl }: Props) {
    const form = useForm({
        fulfillment_policy_id: account?.fulfillment_policy_id ?? '',
        payment_policy_id: account?.payment_policy_id ?? '',
        return_policy_id: account?.return_policy_id ?? '',
    });

    if (policiesError || !policies) {
        return (
            <Panel title="Listing policies">
                <p className="text-sm text-destructive">
                    Couldn’t load your business policies from eBay:{' '}
                    {policiesError ?? 'no response.'}
                </p>
                <p className="mt-1 text-sm text-muted-foreground">
                    Reload the page to try again. If it keeps failing,
                    disconnect and reconnect eBay.
                </p>
            </Panel>
        );
    }

    const missing =
        policies.fulfillment.length === 0 ||
        policies.payment.length === 0 ||
        policies.return.length === 0;
    const fields = [
        {
            key: 'fulfillment_policy_id',
            label: 'Shipping policy',
            options: policies.fulfillment,
        },
        {
            key: 'payment_policy_id',
            label: 'Payment policy',
            options: policies.payment,
        },
        {
            key: 'return_policy_id',
            label: 'Return policy',
            options: policies.return,
        },
    ] as const;

    return (
        <Panel title="Listing policies">
            <form
                className="space-y-4"
                onSubmit={(event) => {
                    event.preventDefault();
                    form.put(ebay.settings.policies.url(), {
                        preserveScroll: true,
                    });
                }}
            >
                {missing && (
                    <p className="text-sm text-muted-foreground">
                        eBay business policies are created in Seller Hub. Add
                        any that are missing under{' '}
                        <a
                            href={`${sellerHubUrl.replace(/\/sh\/ovw$/, '')}/bp/manage`}
                            target="_blank"
                            rel="noreferrer"
                            className="text-primary hover:underline"
                        >
                            Business policies
                        </a>
                        , then reload this page.
                    </p>
                )}
                <div className="grid gap-4 sm:grid-cols-3">
                    {fields.map((field) => (
                        <Field
                            key={field.key}
                            label={field.label}
                            htmlFor={field.key}
                            error={form.errors[field.key]}
                        >
                            <NativeSelect
                                id={field.key}
                                value={form.data[field.key]}
                                onChange={(event) =>
                                    form.setData(field.key, event.target.value)
                                }
                            >
                                <option value="">Choose…</option>
                                {field.options.map((option) => (
                                    <option key={option.id} value={option.id}>
                                        {option.name}
                                    </option>
                                ))}
                            </NativeSelect>
                        </Field>
                    ))}
                </div>
                <Button type="submit" disabled={form.processing}>
                    Save policies
                </Button>
            </form>
        </Panel>
    );
}

function Location({ hasLocation }: { hasLocation: boolean }) {
    const { errors } = usePage().props as { errors: Record<string, string> };
    const form = useForm({
        postal_code: '',
        country: 'US',
        city: '',
        state: '',
    });

    return (
        <Panel title="Item location">
            <form
                className="space-y-4"
                onSubmit={(event) => {
                    event.preventDefault();
                    form.put(ebay.settings.location.url(), {
                        preserveScroll: true,
                        onSuccess: () => form.reset(),
                    });
                }}
            >
                <p className="text-sm text-muted-foreground">
                    {hasLocation
                        ? 'Your item location is set. Save a new address here if you move.'
                        : 'eBay shows buyers where an item ships from and uses it to estimate shipping. Enter where you ship your cards from.'}
                </p>
                <div className="grid gap-4 sm:grid-cols-4">
                    <Field
                        label="ZIP or postal code"
                        htmlFor="postal_code"
                        error={form.errors.postal_code}
                    >
                        <Input
                            id="postal_code"
                            value={form.data.postal_code}
                            onChange={(event) =>
                                form.setData('postal_code', event.target.value)
                            }
                            required
                        />
                    </Field>
                    <Field
                        label="Country"
                        htmlFor="country"
                        error={form.errors.country}
                        hint="Two-letter code"
                    >
                        <Input
                            id="country"
                            maxLength={2}
                            value={form.data.country}
                            onChange={(event) =>
                                form.setData('country', event.target.value)
                            }
                            required
                        />
                    </Field>
                    <Field
                        label="City"
                        htmlFor="city"
                        error={form.errors.city}
                        hint="Optional"
                    >
                        <Input
                            id="city"
                            value={form.data.city}
                            onChange={(event) =>
                                form.setData('city', event.target.value)
                            }
                        />
                    </Field>
                    <Field
                        label="State"
                        htmlFor="state"
                        error={form.errors.state}
                        hint="Optional"
                    >
                        <Input
                            id="state"
                            value={form.data.state}
                            onChange={(event) =>
                                form.setData('state', event.target.value)
                            }
                        />
                    </Field>
                </div>
                {errors.account && (
                    <p className="text-sm text-destructive">{errors.account}</p>
                )}
                <Button type="submit" disabled={form.processing}>
                    {hasLocation ? 'Update location' : 'Save location'}
                </Button>
            </form>
        </Panel>
    );
}
