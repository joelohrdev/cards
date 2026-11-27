import { useState } from 'react';
import { TagChip } from '@/components/cards/tag-chip';
import { controlClass } from '@/components/kit/field';
import { SuggestInput } from '@/components/kit/suggest-input';
import { cn } from '@/lib/utils';
import type { TagSummary } from '@/types';

/**
 * Free-form tag entry. Typing suggests existing tags (matching words first); Enter or comma
 * adds what was typed as a new tag.
 */
export function TagInput({
    value,
    onChange,
    suggestions,
    id,
}: {
    value: string[];
    onChange: (tags: string[]) => void;
    suggestions: TagSummary[];
    id?: string;
}) {
    const [draft, setDraft] = useState('');
    const colorFor = (name: string) =>
        suggestions.find((tag) => tag.name.toLowerCase() === name.toLowerCase())
            ?.color ?? 'slate';

    function add(raw: string) {
        const names = raw
            .split(',')
            .map((name) => name.trim())
            .filter(Boolean)
            .map(
                (name) =>
                    suggestions.find(
                        (tag) => tag.name.toLowerCase() === name.toLowerCase(),
                    )?.name ?? name,
            );
        const next = [...value];

        for (const name of names) {
            if (
                !next.some(
                    (existing) => existing.toLowerCase() === name.toLowerCase(),
                )
            ) {
                next.push(name);
            }
        }

        onChange(next);
        setDraft('');
    }

    const unused = suggestions.filter(
        (tag) =>
            !value.some(
                (name) => name.toLowerCase() === tag.name.toLowerCase(),
            ),
    );

    return (
        <div>
            <div
                className={cn(
                    controlClass,
                    'h-auto min-h-9 flex-wrap items-center gap-1 py-1.5',
                )}
            >
                {value.map((name) => (
                    <TagChip
                        key={name}
                        name={name}
                        color={colorFor(name)}
                        onRemove={() =>
                            onChange(value.filter((entry) => entry !== name))
                        }
                    />
                ))}
                <SuggestInput
                    bare
                    field="tag"
                    id={id}
                    value={draft}
                    exclude={value}
                    onPick={add}
                    onChange={(next) => {
                        if (next.endsWith(',')) {
                            add(next);
                        } else {
                            setDraft(next);
                        }
                    }}
                    onKeyDown={(event) => {
                        if (event.key === 'Enter' && draft.trim()) {
                            event.preventDefault();
                            add(draft);
                        } else if (
                            event.key === 'Backspace' &&
                            draft === '' &&
                            value.length
                        ) {
                            onChange(value.slice(0, -1));
                        }
                    }}
                    onBlur={() => draft.trim() && add(draft)}
                    placeholder={
                        value.length ? '' : 'PC, flip, grading candidate…'
                    }
                />
            </div>
            {unused.length > 0 && (
                <div className="mt-1.5 flex flex-wrap gap-1">
                    {unused.slice(0, 8).map((tag) => (
                        <button
                            key={tag.id}
                            type="button"
                            onClick={() => add(tag.name)}
                            className="opacity-70 hover:opacity-100"
                        >
                            <TagChip name={`+ ${tag.name}`} color={tag.color} />
                        </button>
                    ))}
                </div>
            )}
        </div>
    );
}
