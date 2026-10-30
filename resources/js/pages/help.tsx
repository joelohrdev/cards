import { Head, Link } from '@inertiajs/react';
import { PageHeader } from '@/components/kit/page-header';
import { Panel } from '@/components/kit/panel';
import { dashboard } from '@/routes';
import collection from '@/routes/collection';
import ebay from '@/routes/ebay';
import exportsRoutes from '@/routes/exports';
import grading from '@/routes/grading';
import imports from '@/routes/imports';
import locations from '@/routes/locations';
import sales from '@/routes/sales';
import sets from '@/routes/sets';
import shareLinks from '@/routes/share-links';
import tags from '@/routes/tags';
import wantList from '@/routes/want-list';

type Section = {
    id: string;
    title: string;
    body: React.ReactNode;
};

function Go({
    href,
    children,
}: {
    href: Parameters<typeof Link>[0]['href'];
    children: React.ReactNode;
}) {
    return (
        <Link
            href={href}
            className="font-medium text-primary underline underline-offset-2 hover:no-underline"
        >
            {children}
        </Link>
    );
}

function Kbd({ children }: { children: React.ReactNode }) {
    return (
        <kbd className="rounded border bg-muted px-1.5 py-0.5 font-sans text-xs font-medium">
            {children}
        </kbd>
    );
}

function Steps({ children }: { children: React.ReactNode }) {
    return <ol className="list-decimal space-y-1.5 pl-5">{children}</ol>;
}

function Points({ children }: { children: React.ReactNode }) {
    return <ul className="list-disc space-y-1.5 pl-5">{children}</ul>;
}

const sections: Section[] = [
    {
        id: 'start',
        title: 'Getting started',
        body: (
            <>
                <p>
                    Everyone who signs in works from the same collection: every
                    card, what was paid and what it’s worth, plus the tags,
                    storage, grading, sales and want list. Anything one of you
                    adds or changes, the others see right away. The catalog of
                    sets and checklists is shared the same way, so a set entered
                    once is ready for everybody.
                </p>
                <p>A good first hour looks like this:</p>
                <Steps>
                    <li>
                        Already keep a spreadsheet? Bring it in with{' '}
                        <Go href={imports.index()}>Import</Go> (see Importing a
                        spreadsheet below).
                    </li>
                    <li>
                        Set up your boxes and binders under{' '}
                        <Go href={locations.index()}>Storage</Go> so every card
                        gets an address as you add it.
                    </li>
                    <li>
                        Add cards with{' '}
                        <Go href={collection.create()}>Add a card</Go>, or{' '}
                        <Go href={collection.quickAdd()}>Quick add</Go> for a
                        stack from one set.
                    </li>
                    <li>
                        Check the <Go href={dashboard()}>Overview</Go>. Its
                        “Needs attention” list shows cards without a value,
                        without photos or without a storage spot.
                    </li>
                </Steps>
            </>
        ),
    },
    {
        id: 'adding',
        title: 'Adding cards',
        body: (
            <>
                <p>
                    <Go href={collection.create()}>Add a card</Go> handles one
                    card at a time:
                </p>
                <Steps>
                    <li>
                        Under “Which card?”, use <em>Find in catalog</em> to
                        search by player, year, set or number. If the card isn’t
                        there, switch to <em>New card</em>, pick or create the
                        set, and type the card number and player.
                    </li>
                    <li>
                        Add a front and back photo if you have them. JPG, PNG or
                        WebP work.
                    </li>
                    <li>
                        Choose the parallel (or add one that isn’t listed) and a
                        serial number for numbered cards.
                    </li>
                    <li>
                        Set the condition: a raw grade, or the grader, grade and
                        cert number for a slab.
                    </li>
                    <li>
                        Fill in what you paid, what it’s worth now, where it
                        lives and any tags, then press{' '}
                        <em>Add to collection</em>.{' '}
                        <em>Add and enter another</em> keeps the set and team
                        filled in for the next card.
                    </li>
                </Steps>
                <p>
                    <strong>Identify from photo</strong> reads a picture of the
                    card and fills in what it can: year, set, number, player
                    and, for slabs, the grade. Always check the result before
                    saving. It needs an Anthropic API key in the server
                    settings.
                </p>
                <p>
                    <strong>Several copies?</strong> Set the quantity. Each copy
                    is tracked on its own so you can sell or grade them
                    separately.
                </p>
            </>
        ),
    },
    {
        id: 'quick-add',
        title: 'Quick add and checklists',
        body: (
            <>
                <p>
                    <Go href={collection.quickAdd()}>Quick add</Go> is the
                    fastest way through a stack from one set. Pick the set, type
                    a card number and press <Kbd>Enter</Kbd>. The player fills
                    in from the checklist, and the next row is ready.
                </p>
                <p>
                    Checklists live under{' '}
                    <Go href={sets.index()}>Sets & checklists</Go>. To add one,
                    open the set and paste the checklist, one card per line:
                </p>
                <pre className="overflow-x-auto rounded-md bg-muted px-3 py-2 text-sm">
                    {`12 Mike Trout - Los Angeles Angels RC\n27 Shohei Ohtani - Los Angeles Dodgers /99`}
                </pre>
                <p>
                    Tab-separated columns copied from a spreadsheet work too.
                    Tags such as RC, AU, AUTO, SP, SSP, RELIC, PATCH and /99 at
                    the end of a line are picked up.
                </p>
                <p>
                    On a set’s page you can see what you own and what’s missing.
                    Tick cards, then choose <em>Add to collection</em> to record
                    them, or <em>Want these</em> to put them on your want list.
                </p>
            </>
        ),
    },
    {
        id: 'finding',
        title: 'Finding a card',
        body: (
            <>
                <p>
                    Press <Kbd>⌘K</Kbd> (or <Kbd>Ctrl K</Kbd>, or <Kbd>/</Kbd>)
                    from anywhere to jump straight to a card. For more control,
                    use the search box and filters on{' '}
                    <Go href={collection.index()}>All cards</Go>.
                </p>
                <p>Search understands collector shorthand. Mix and match:</p>
                <div className="overflow-x-auto">
                    <table className="w-full text-sm">
                        <thead className="text-left text-muted-foreground">
                            <tr>
                                <th className="py-1.5 pr-4 font-medium">
                                    Type
                                </th>
                                <th className="py-1.5 font-medium">Finds</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y">
                            {[
                                ['griffey 1989', 'Griffey cards from 1989'],
                                ['2023-24', 'cards from the 2023-24 season'],
                                ['#23', 'card number 23'],
                                [
                                    'psa 10',
                                    'PSA 10 slabs (also bgs 9.5, sgc 9…)',
                                ],
                                ['/99', 'cards numbered to 99'],
                                ['rc auto', 'rookie autographs'],
                                [
                                    'relic, sp',
                                    'memorabilia cards, short prints',
                                ],
                                [
                                    'graded, raw',
                                    'slabbed or ungraded cards only',
                                ],
                            ].map(([query, finds]) => (
                                <tr key={query}>
                                    <td className="py-1.5 pr-4">
                                        <Kbd>{query}</Kbd>
                                    </td>
                                    <td className="py-1.5">{finds}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
                <p>
                    Any other words must match a player, set, brand, team,
                    parallel, subset, card number, cert number, tag or note.
                    Spelling has to be right: “griffy” won’t find Griffey.
                </p>
            </>
        ),
    },
    {
        id: 'organizing',
        title: 'Tags, storage and bulk changes',
        body: (
            <>
                <p>
                    <Go href={tags.index()}>Tags</Go> are shared labels: PC,
                    flip, grading candidates, show finds. Add them on a card,
                    then search for a tag’s name to see its cards.
                </p>
                <p>
                    <Go href={locations.index()}>Storage</Go> maps your boxes,
                    binders and cases. Nest rows or pages inside a box, and use
                    the “Slot / page / row” field on a card for its exact spot.
                    A card’s page always shows where it lives.
                </p>
                <p>
                    To change many cards at once, tick them on{' '}
                    <Go href={collection.index()}>All cards</Go>. A bar appears
                    that can add or remove tags, move them to storage, change
                    their status or set their value.
                </p>
            </>
        ),
    },
    {
        id: 'values',
        title: 'Values and comps',
        body: (
            <>
                <p>
                    The app doesn’t pull prices automatically. You set each
                    card’s value yourself, so you decide what counts.
                </p>
                <Points>
                    <li>
                        Type a value in “Worth now” when adding or editing a
                        card.
                    </li>
                    <li>
                        Or record <strong>sold comps</strong> on the card’s
                        page: sales you’ve seen on eBay or at shows, with price
                        and date. Use the average of the comps that match the
                        card’s parallel and grade as its value.
                    </li>
                    <li>
                        Change many values at once with “Set value” in the bulk
                        bar.
                    </li>
                </Points>
                <p>
                    Every night the app takes a snapshot of your collection’s
                    total value. The Overview chart shows how it changes over
                    time.
                </p>
            </>
        ),
    },
    {
        id: 'want-list',
        title: 'Want list',
        body: (
            <p>
                The <Go href={wantList.index()}>Want list</Go> holds the cards
                you’re hunting, with the most you’ll pay for each. Add cards
                with “Want these” on any set’s page. When you add a card you
                wanted to your collection, it comes off the list by itself. The
                page works well on a phone at a card show.
            </p>
        ),
    },
    {
        id: 'grading',
        title: 'Grading submissions',
        body: (
            <>
                <p>
                    <Go href={grading.index()}>Grading</Go> tracks cards you
                    send to PSA, BGS, SGC and others. Tracking is manual: you
                    move the submission along as you hear from the grader.
                </p>
                <Steps>
                    <li>
                        Create a submission and add raw cards you own, with each
                        card’s declared value and grading fee.
                    </li>
                    <li>
                        Update the status as it moves: Preparing → Submitted →
                        Received → Grading → Shipped back. Once it’s submitted,
                        its cards show as “At grading”.
                    </li>
                    <li>
                        When the slabs arrive, choose <em>Record results</em>{' '}
                        and enter each grade and cert number. The cards become
                        graded cards in your collection and the submission
                        closes.
                    </li>
                    <li>
                        Tick the cost option to add each card’s fee and a share
                        of shipping to what you paid for it.
                    </li>
                </Steps>
            </>
        ),
    },
    {
        id: 'selling',
        title: 'Selling and trading',
        body: (
            <>
                <p>
                    Sold a card at a show or to a friend? On the card’s page
                    choose <em>Record a sale</em> and enter the price, fees and
                    shipping. If you swapped it instead, use{' '}
                    <em>Mark as traded instead</em>.
                </p>
                <p>
                    <Go href={sales.index()}>Sales</Go> lists every sale with
                    profit over what you paid, and can export them as a CSV for
                    your records or taxes.
                </p>
            </>
        ),
    },
    {
        id: 'ebay',
        title: 'Listing on eBay',
        body: (
            <>
                <Steps>
                    <li>
                        Open <Go href={ebay.settings()}>eBay settings</Go> and
                        connect your seller account.
                    </li>
                    <li>
                        Choose your shipping, payment and return policies, and
                        save your item location. These are made in eBay Seller
                        Hub; the app uses the ones you already have.
                    </li>
                    <li>
                        On a card’s page, choose to list it on eBay. Check the
                        title, price and condition, then save it as a draft or
                        publish it. The card’s photos go with the listing.
                    </li>
                </Steps>
                <p>
                    Every 15 minutes the app checks eBay for orders. When a card
                    sells, it records the sale and marks the card as sold. See
                    everything you’ve listed under{' '}
                    <Go href={ebay.listings.index()}>eBay</Go>.
                </p>
            </>
        ),
    },
    {
        id: 'import',
        title: 'Importing a spreadsheet',
        body: (
            <Steps>
                <li>
                    Save your spreadsheet as a CSV file and upload it on{' '}
                    <Go href={imports.index()}>Import</Go>.
                </li>
                <li>
                    Match each column to a card field: year, set, number,
                    player, grade, price paid and so on. Columns you don’t match
                    are ignored.
                </li>
                <li>
                    Start the import. Nothing is saved until you’ve checked the
                    matches. Rows that can’t be read are listed with the reason
                    so you can fix them.
                </li>
            </Steps>
        ),
    },
    {
        id: 'sharing',
        title: 'Exports and share links',
        body: (
            <>
                <p>
                    <Go href={exportsRoutes.index()}>Export</Go> downloads your
                    whole collection as a CSV for backups, or builds an
                    inventory with photos and values to hand to an insurer.
                </p>
                <p>
                    <Go href={shareLinks.index()}>Share links</Go> make a
                    read-only page of your cards that anyone can open without an
                    account. Limit it to one tag, choose whether to show values,
                    and set an end date if you like. You can turn a link off at
                    any time.
                </p>
            </>
        ),
    },
    {
        id: 'account',
        title: 'Your account',
        body: (
            <p>
                Change your name, email and password from the settings menu. New
                accounts can’t be created from the sign-in page; ask whoever
                runs the app to add you.
            </p>
        ),
    },
];

export default function Help() {
    return (
        <>
            <Head title="Help" />
            <PageHeader
                title="How to use Cards"
                description="Everything the app does, in the order most people need it."
            />

            <div className="grid gap-6 xl:grid-cols-[14rem_1fr]">
                <nav
                    aria-label="Help topics"
                    className="xl:sticky xl:top-6 xl:self-start"
                >
                    <ul className="flex flex-wrap gap-2 text-sm xl:flex-col xl:gap-1">
                        {sections.map((section) => (
                            <li key={section.id}>
                                <a
                                    href={`#${section.id}`}
                                    className="block rounded-md border px-3 py-1.5 text-muted-foreground hover:bg-muted hover:text-foreground xl:border-0 xl:px-2 xl:py-1"
                                >
                                    {section.title}
                                </a>
                            </li>
                        ))}
                    </ul>
                </nav>

                <div className="max-w-3xl space-y-6">
                    {sections.map((section) => (
                        <div
                            key={section.id}
                            id={section.id}
                            className="scroll-mt-6"
                        >
                            <Panel title={section.title}>
                                <div className="space-y-3 leading-relaxed">
                                    {section.body}
                                </div>
                            </Panel>
                        </div>
                    ))}
                </div>
            </div>
        </>
    );
}
