import { Plus } from 'lucide-react';
import { useId, useState } from 'react';
import { controlClass } from '@/components/kit/field';
import { useLookup } from '@/hooks/use-lookup';
import { cn } from '@/lib/utils';
import { suggestions as suggestionsRoute } from '@/routes';

/**
 * Free-text fields that work like categories. Keep in step with SuggestionController::FIELDS.
 */
export type SuggestionField =
    | 'tag'
    | 'purchase_source'
    | 'manufacturer'
    | 'player'
    | 'team'
    | 'subset'
    | 'service_level'
    | 'buyer'
    | 'comp_source';

type Props = Omit<
    React.ComponentProps<'input'>,
    'value' | 'defaultValue' | 'onChange'
> & {
    field: SuggestionField;
    /** Controlled value; leave out (with `defaultValue`) inside an uncontrolled <Form>. */
    value?: string;
    defaultValue?: string | null;
    onChange?: (value: string) => void;
    /** Called when a suggestion is chosen; by default it replaces the text. */
    onPick?: (value: string) => void;
    /** Values to leave out of the list, e.g. tags already on the card. */
    exclude?: string[];
    /**
     * For a field holding several values (tags "PC, Flip", players "Jordan / Pippen"):
     * suggest and complete only the part after the last separator.
     */
    separator?: ',' | '/';
    /** Render a bare input (for embedding inside another control). */
    bare?: boolean;
};

/**
 * A text input that suggests values already used in this field as you type: ones that start
 * with what you typed first, then the most used. Anything new can still be typed in. When the
 * typed text matches an existing value apart from capitalization, it takes that spelling.
 */
export function SuggestInput({
    field,
    value: controlledValue,
    defaultValue,
    onChange,
    onPick,
    exclude = [],
    separator,
    bare = false,
    className,
    onKeyDown,
    onFocus,
    onBlur,
    ...inputProps
}: Props) {
    const listId = useId();
    const [ownValue, setOwnValue] = useState(defaultValue ?? '');
    const [open, setOpen] = useState(false);
    const [highlighted, setHighlighted] = useState(-1);
    const value = controlledValue ?? ownValue;

    const parts = separator ? value.split(separator) : [value];
    const earlier = parts
        .slice(0, -1)
        .map((part) => part.trim())
        .filter(Boolean);
    const typed = (parts[parts.length - 1] ?? '').trim();
    const glue = separator === ',' ? ', ' : ` ${separator} `;

    const { data } = useLookup<{ suggestions: string[] }>(
        open ? suggestionsRoute.url(field, { query: { q: typed } }) : null,
        120,
    );

    const excluded = new Set(
        [...exclude, ...earlier].map((entry) => entry.toLowerCase()),
    );
    const options = (data?.suggestions ?? []).filter(
        (option) => !excluded.has(option.toLowerCase()),
    );
    const exactMatch = options.find(
        (option) => option.toLowerCase() === typed.toLowerCase(),
    );
    const isNew = typed !== '' && exactMatch === undefined;
    const showList = open && (options.length > 0 || isNew);

    function setValue(next: string) {
        if (controlledValue === undefined) {
            setOwnValue(next);
        }

        onChange?.(next);
    }

    function pick(option: string) {
        setHighlighted(-1);

        if (onPick) {
            onPick(option);

            return;
        }

        if (separator) {
            // Comma lists usually go on (more tags); a "/" list is usually done.
            setValue(
                [...earlier, option].join(glue) +
                    (separator === ',' ? glue : ''),
            );

            if (separator === ',') {
                return;
            }
        } else {
            setValue(option);
        }

        setOpen(false);
    }

    function handleKeyDown(event: React.KeyboardEvent<HTMLInputElement>) {
        if (showList && event.key === 'ArrowDown') {
            event.preventDefault();
            event.stopPropagation();
            setHighlighted((current) =>
                Math.min(current + 1, options.length - 1),
            );

            return;
        }

        if (showList && event.key === 'ArrowUp') {
            event.preventDefault();
            event.stopPropagation();
            setHighlighted((current) => Math.max(current - 1, -1));

            return;
        }

        if (event.key === 'Escape' && showList) {
            event.preventDefault();
            event.stopPropagation();
            setOpen(false);

            return;
        }

        // Picking with Enter mustn't also submit a form or add a Quick add row.
        if (event.key === 'Enter' && showList && options[highlighted]) {
            event.preventDefault();
            event.stopPropagation();
            pick(options[highlighted]);

            return;
        }

        onKeyDown?.(event);
    }

    return (
        <div className={cn('relative', bare ? 'min-w-32 flex-1' : 'w-full')}>
            <input
                {...inputProps}
                value={value}
                autoComplete="off"
                role="combobox"
                aria-autocomplete="list"
                aria-expanded={showList}
                aria-controls={listId}
                aria-activedescendant={
                    highlighted >= 0 ? `${listId}-${highlighted}` : undefined
                }
                onChange={(event) => {
                    setValue(event.target.value);
                    setOpen(true);
                    setHighlighted(-1);
                }}
                onFocus={(event) => {
                    setOpen(true);
                    onFocus?.(event);
                }}
                onBlur={(event) => {
                    setOpen(false);

                    if (exactMatch && exactMatch !== typed) {
                        setValue(
                            separator
                                ? [...earlier, exactMatch].join(glue)
                                : exactMatch,
                        );
                    }

                    onBlur?.(event);
                }}
                onKeyDown={handleKeyDown}
                className={cn(
                    bare ? 'w-full bg-transparent outline-none' : controlClass,
                    className,
                )}
            />
            {showList && (
                <ul
                    id={listId}
                    role="listbox"
                    className="absolute top-full left-0 z-50 mt-1 max-h-64 w-full min-w-48 overflow-auto rounded-md border bg-popover p-1 text-sm text-popover-foreground shadow-md"
                >
                    {options.map((option, index) => (
                        <li
                            key={option}
                            id={`${listId}-${index}`}
                            role="option"
                            aria-selected={index === highlighted}
                            onMouseDown={(event) => {
                                event.preventDefault();
                                pick(option);
                            }}
                            onMouseEnter={() => setHighlighted(index)}
                            className={cn(
                                'cursor-pointer rounded-sm px-2 py-2',
                                index === highlighted && 'bg-accent',
                            )}
                        >
                            <Highlight text={option} typed={typed} />
                        </li>
                    ))}
                    {isNew && (
                        <li
                            role="presentation"
                            className={cn(
                                'flex items-center gap-1.5 px-2 py-2 text-muted-foreground',
                                options.length > 0 && 'border-t',
                            )}
                        >
                            <Plus className="size-3.5" />
                            New: “{typed}”
                        </li>
                    )}
                </ul>
            )}
        </div>
    );
}

/**
 * The suggestion with the typed part in bold.
 */
function Highlight({ text, typed }: { text: string; typed: string }) {
    const start =
        typed === '' ? -1 : text.toLowerCase().indexOf(typed.toLowerCase());

    if (start === -1) {
        return <>{text}</>;
    }

    return (
        <>
            {text.slice(0, start)}
            <strong className="font-semibold">
                {text.slice(start, start + typed.length)}
            </strong>
            {text.slice(start + typed.length)}
        </>
    );
}
