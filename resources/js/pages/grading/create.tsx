import { Form, Head, Link } from '@inertiajs/react';
import {
    Field,
    MoneyInput,
    NativeSelect,
    Textarea,
} from '@/components/kit/field';
import { PageHeader } from '@/components/kit/page-header';
import { Panel } from '@/components/kit/panel';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import grading from '@/routes/grading';
import type { Option } from '@/types';

export default function GradingCreate({ graders }: { graders: Option[] }) {
    return (
        <>
            <Head title="New grading submission" />

            <PageHeader
                eyebrow={
                    <Link
                        href={grading.index()}
                        className="hover:text-foreground"
                    >
                        Grading
                    </Link>
                }
                title="New submission"
                description="Start with the basics. You’ll add cards and fees on the next page."
            />

            <Panel className="max-w-2xl">
                <Form
                    action={grading.store.url()}
                    method="post"
                    className="grid gap-4 sm:grid-cols-2"
                >
                    {({ errors, processing }) => (
                        <>
                            <Field
                                label="Grader"
                                htmlFor="grader"
                                error={errors.grader}
                            >
                                <NativeSelect
                                    id="grader"
                                    name="grader"
                                    defaultValue="PSA"
                                    required
                                >
                                    {graders.map((grader) => (
                                        <option
                                            key={grader.value}
                                            value={grader.value}
                                        >
                                            {grader.label}
                                        </option>
                                    ))}
                                </NativeSelect>
                            </Field>
                            <Field
                                label="Service level"
                                htmlFor="service_level"
                                error={errors.service_level}
                                hint="e.g. Value, Regular, Express"
                            >
                                <Input
                                    id="service_level"
                                    name="service_level"
                                />
                            </Field>
                            <Field
                                label="Submission number"
                                htmlFor="reference"
                                error={errors.reference}
                                hint="From the grader’s order form, if you have it"
                            >
                                <Input id="reference" name="reference" />
                            </Field>
                            <Field
                                label="Shipping cost"
                                htmlFor="shipping_cost"
                                error={errors.shipping_cost}
                                hint="Both ways, insurance included"
                            >
                                <MoneyInput
                                    id="shipping_cost"
                                    name="shipping_cost"
                                    placeholder="0.00"
                                />
                            </Field>
                            <Field
                                label="Notes"
                                htmlFor="notes"
                                error={errors.notes}
                                className="sm:col-span-2"
                            >
                                <Textarea id="notes" name="notes" />
                            </Field>
                            <div className="flex gap-2 sm:col-span-2">
                                <Button type="submit" disabled={processing}>
                                    Start submission
                                </Button>
                                <Button asChild variant="outline">
                                    <Link href={grading.index()}>Cancel</Link>
                                </Button>
                            </div>
                        </>
                    )}
                </Form>
            </Panel>
        </>
    );
}
