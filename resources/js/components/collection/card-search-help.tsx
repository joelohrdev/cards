import { Info } from 'lucide-react';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';

/**
 * The info button beside "Which card?" that explains what the card search looks through.
 */
export function CardSearchHelp() {
    return (
        <Dialog>
            <DialogTrigger
                className="inline-flex size-8 items-center justify-center rounded-full text-muted-foreground transition-colors hover:bg-muted hover:text-foreground"
                aria-label="What is this search for?"
            >
                <Info className="size-[18px]" />
            </DialogTrigger>
            <DialogContent className="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Finding the card</DialogTitle>
                    <DialogDescription>
                        The search saves you typing out the card’s details.
                    </DialogDescription>
                </DialogHeader>
                <ul className="list-disc space-y-2.5 pl-5 text-sm">
                    <li>
                        It looks through cards already in the app: every set
                        checklist pasted in under Sets &amp; checklists, and
                        every card anyone has added before.
                    </li>
                    <li>
                        Type a player, year, brand, set or card number, in any
                        mix. For example “1989 upper deck griffey” or “prizm
                        #1”.
                    </li>
                    <li>
                        Picking a result fills in the set, number, player, team
                        and flags like rookie or auto. You only add the details
                        of your copy.
                    </li>
                    <li>
                        Not there? Choose <strong>Enter it by hand</strong>.
                        Once it’s saved, it shows up in the search next time.
                    </li>
                    <li>It doesn’t search eBay or the internet.</li>
                </ul>
            </DialogContent>
        </Dialog>
    );
}
