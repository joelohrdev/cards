import { Form, Head, Link } from '@inertiajs/react';
import { ChecklistHelp } from '@/components/catalog/checklist-help';
import { Field, NativeSelect, Textarea } from '@/components/kit/field';
import { PageHeader } from '@/components/kit/page-header';
import { Panel } from '@/components/kit/panel';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import sets from '@/routes/sets';
import type { Option } from '@/types';

export default function SetsCreate({
    sports,
    manufacturers,
}: {
    sports: Option[];
    manufacturers: string[];
}) {
    return (
        <>
            <Head title="New set" />
            <PageHeader
                eyebrow={
                    <Link href={sets.index()} className="hover:text-foreground">
                        ‹ Sets & checklists
                    </Link>
                }
                title="New set"
            />

            <Form {...sets.store.form()} className="grid max-w-4xl gap-6">
                {({ errors, processing }) => (
                    <>
                        <Panel title="About the set">
                            <div className="grid gap-4 sm:grid-cols-6">
                                <Field
                                    label="Year"
                                    error={errors.year}
                                    className="sm:col-span-1"
                                >
                                    <Input
                                        name="year"
                                        inputMode="numeric"
                                        required
                                        placeholder="1987"
                                    />
                                </Field>
                                <Field
                                    label="Season"
                                    hint="Optional, e.g. 2023-24"
                                    error={errors.season}
                                    className="sm:col-span-1"
                                >
                                    <Input name="season" />
                                </Field>
                                <Field
                                    label="Brand"
                                    error={errors.manufacturer}
                                    className="sm:col-span-2"
                                >
                                    <Input
                                        name="manufacturer"
                                        list="manufacturers"
                                        placeholder="Topps"
                                    />
                                    <datalist id="manufacturers">
                                        {manufacturers.map((name) => (
                                            <option key={name} value={name} />
                                        ))}
                                    </datalist>
                                </Field>
                                <Field
                                    label="Sport"
                                    error={errors.sport}
                                    className="sm:col-span-2"
                                >
                                    <NativeSelect
                                        name="sport"
                                        defaultValue="baseball"
                                    >
                                        {sports.map((sport) => (
                                            <option
                                                key={sport.value}
                                                value={sport.value}
                                            >
                                                {sport.label}
                                            </option>
                                        ))}
                                    </NativeSelect>
                                </Field>
                                <Field
                                    label="Set name"
                                    error={errors.name}
                                    className="sm:col-span-4"
                                >
                                    <Input
                                        name="name"
                                        required
                                        placeholder="Base, Chrome, Update, Prizm…"
                                    />
                                </Field>
                                <Field
                                    label="Cards in set"
                                    hint="Used for completion %"
                                    error={errors.card_count}
                                    className="sm:col-span-2"
                                >
                                    <Input
                                        name="card_count"
                                        inputMode="numeric"
                                        placeholder="792"
                                    />
                                </Field>
                            </div>
                        </Panel>

                        <Panel title="Checklist">
                            <Field
                                label="Paste the checklist (optional)"
                                hint={<ChecklistHelp />}
                                error={errors.checklist}
                            >
                                <Textarea
                                    name="checklist"
                                    rows={12}
                                    className="font-mono text-xs"
                                    placeholder={
                                        '1 Rickey Henderson - Oakland Athletics\n2 Roger Clemens - Boston Red Sox\n320 Barry Bonds - Pittsburgh Pirates RC'
                                    }
                                />
                            </Field>
                        </Panel>

                        <div>
                            <Button
                                type="submit"
                                size="lg"
                                disabled={processing}
                            >
                                Create set
                            </Button>
                        </div>
                    </>
                )}
            </Form>
        </>
    );
}
