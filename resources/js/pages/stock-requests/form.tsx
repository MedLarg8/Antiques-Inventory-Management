import { Link, router, useForm } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import InputError from '@/components/input-error';
import {
    OrganizationPage,
    selectClass,
    EmptyState,
} from '@/components/organization';
import { Input } from '@/components/ui/input';
import { Button } from '@/components/ui/button';
import type { StockChoice, StockRequest } from '@/types/stock';
import type { StorageArea } from '@/types/organization';
export default function RequestForm({
    stockRequest,
    area,
}: {
    stockRequest: StockRequest | null;
    area: StorageArea;
}) {
    const form = useForm({
        operation_type: stockRequest?.operation_type ?? 'entry',
        reason: stockRequest?.reason ?? '',
        source_or_destination: stockRequest?.source_or_destination ?? '',
        lines:
            stockRequest?.lines.map((l) => ({
                item_id: l.item_id,
                quantity: l.quantity,
            })) ?? ([] as { item_id: number; quantity: string }[]),
    });
    const [selected, setSelected] = useState<Record<number, StockChoice>>(() =>
        Object.fromEntries(
            stockRequest?.lines.map((l) => [
                l.item_id,
                {
                    id: l.item_id,
                    name: l.item_name_snapshot,
                    reference_code: l.item_reference_snapshot,
                    unit_of_measure: l.unit_snapshot,
                    availability: null,
                },
            ]) ?? [],
        ),
    );
    const [search, setSearch] = useState('');
    const [results, setResults] = useState<StockChoice[]>([]);
    const [loading, setLoading] = useState(false);
    const [searchError, setSearchError] = useState('');
    const [saving, setSaving] = useState(false);
    useEffect(() => {
        const controller = new AbortController();
        const timer = setTimeout(() => {
            setLoading(true);
            setSearchError('');
            fetch(
                '/stock-requests/items?search=' + encodeURIComponent(search),
                {
                    headers: { Accept: 'application/json' },
                    signal: controller.signal,
                },
            )
                .then(async (response) => {
                    if (!response.ok)
                        throw new Error(
                            'Item search is unavailable. Check your assignment or sign in again.',
                        );
                    return response.json() as Promise<{ items: StockChoice[] }>;
                })
                .then((data) => setResults(data.items))
                .catch((error: Error) => {
                    if (error.name !== 'AbortError')
                        setSearchError(error.message);
                })
                .finally(() => {
                    if (!controller.signal.aborted) setLoading(false);
                });
        }, 250);
        return () => {
            clearTimeout(timer);
            controller.abort();
        };
    }, [search]);
    function save(submit: boolean) {
        setSaving(true);
        const options = {
            onError: () => setSaving(false),
            onFinish: () => {
                if (!submit) setSaving(false);
            },
            onSuccess: (page: { props: Record<string, unknown> }) => {
                if (!submit) {
                    setSaving(false);
                    return;
                }
                const saved = page.props.stockRequest as StockRequest;
                router.post(
                    '/stock-requests/' + saved.id + '/submit',
                    {},
                    { onFinish: () => setSaving(false) },
                );
            },
        };
        if (stockRequest)
            form.put('/stock-requests/' + stockRequest.id, options);
        else form.post('/stock-requests', options);
    }
    const errors = form.errors as Record<string, string>;
    return (
        <OrganizationPage
            title={
                stockRequest
                    ? 'Edit ' + stockRequest.request_number
                    : 'Create stock request'
            }
            description={
                area.city.name +
                ' / ' +
                area.name +
                ' — quantities change only after central approval and successful execution.'
            }
        >
            <p className="rounded border bg-muted/30 p-4 text-sm">
                Only quantity-based consumables and reusable equipment are
                supported. Individually tracked assets require a later
                asset-location workflow. Missing or uninitialized stock requires
                a controlled opening balance before execution.
            </p>
            <form
                className="space-y-6"
                onSubmit={(e) => {
                    e.preventDefault();
                    save(false);
                }}
            >
                <div className="grid gap-5 rounded border p-5 sm:grid-cols-2">
                    <label className="space-y-2">
                        <span>Operation</span>
                        <select
                            className={selectClass}
                            value={form.data.operation_type}
                            onChange={(e) =>
                                form.setData(
                                    'operation_type',
                                    e.target.value as 'entry' | 'exit',
                                )
                            }
                        >
                            <option value="entry">
                                Stock entry — physical receipt
                            </option>
                            <option value="exit">
                                Stock exit — physical departure
                            </option>
                        </select>
                    </label>
                    <label className="space-y-2">
                        <span>
                            {form.data.operation_type === 'entry'
                                ? 'Source / reference'
                                : 'Destination / reference'}{' '}
                            (optional)
                        </span>
                        <Input
                            maxLength={500}
                            value={form.data.source_or_destination}
                            onChange={(e) =>
                                form.setData(
                                    'source_or_destination',
                                    e.target.value,
                                )
                            }
                        />
                    </label>
                    <label className="space-y-2 sm:col-span-2">
                        <span>Reason</span>
                        <textarea
                            className="min-h-24 w-full rounded border p-3"
                            required
                            maxLength={10000}
                            value={form.data.reason}
                            onChange={(e) =>
                                form.setData('reason', e.target.value)
                            }
                        />
                    </label>
                </div>
                <section className="space-y-4 rounded border p-5">
                    <h2 className="text-lg font-semibold">Select items</h2>
                    <label className="block space-y-2">
                        <span>Search active quantity-based items</span>
                        <Input
                            value={search}
                            maxLength={100}
                            onChange={(e) => setSearch(e.target.value)}
                            placeholder="Item name or reference"
                        />
                    </label>
                    <InputError message={searchError} />
                    {loading && (
                        <p
                            role="status"
                            className="text-sm text-muted-foreground"
                        >
                            Searching…
                        </p>
                    )}
                    <div className="max-h-64 overflow-y-auto rounded border">
                        {results.map((i) => (
                            <div
                                key={i.id}
                                className="flex flex-wrap items-center justify-between gap-3 border-b p-3 last:border-0"
                            >
                                <div>
                                    <p className="font-medium">{i.name}</p>
                                    <p className="text-sm text-muted-foreground">
                                        {i.reference_code} · {i.unit_of_measure}{' '}
                                        ·{' '}
                                        {i.availability?.quantity === null ||
                                        !i.availability
                                            ? 'Stock not initialized'
                                            : 'Recorded ' +
                                              i.availability.quantity}
                                    </p>
                                </div>
                                <Button
                                    type="button"
                                    variant="outline"
                                    disabled={
                                        form.data.lines.some(
                                            (l) => l.item_id === i.id,
                                        ) ||
                                        saving ||
                                        form.data.lines.length >= 100
                                    }
                                    onClick={() => {
                                        setSelected((s) => ({
                                            ...s,
                                            [i.id]: i,
                                        }));
                                        form.setData('lines', [
                                            ...form.data.lines,
                                            { item_id: i.id, quantity: '' },
                                        ]);
                                    }}
                                >
                                    Add item
                                </Button>
                            </div>
                        ))}
                        {!results.length && !loading && (
                            <p className="p-4 text-sm text-muted-foreground">
                                No matching active quantity-based items.
                            </p>
                        )}
                    </div>
                    <p className="text-xs text-muted-foreground">
                        Search returns up to 20 matches. Refine the search to
                        find more items. A request supports up to 100 lines.
                    </p>
                </section>
                <section className="space-y-4">
                    <h2 className="text-lg font-semibold">
                        Requested quantities
                    </h2>
                    {form.data.lines.length ? (
                        form.data.lines.map((line, index) => {
                            const item = selected[line.item_id];
                            return (
                                <div
                                    key={line.item_id}
                                    className="flex flex-wrap items-end gap-4 rounded border p-4"
                                >
                                    <div className="min-w-48 flex-1">
                                        <p className="font-medium">
                                            {item.name}
                                        </p>
                                        <p className="text-sm text-muted-foreground">
                                            {item.reference_code} ·{' '}
                                            {item.unit_of_measure}
                                        </p>
                                    </div>
                                    <label className="space-y-2">
                                        <span>
                                            Quantity ({item.unit_of_measure})
                                        </span>
                                        <Input
                                            inputMode="decimal"
                                            required
                                            value={line.quantity}
                                            onChange={(e) =>
                                                form.setData(
                                                    'lines',
                                                    form.data.lines.map(
                                                        (l, n) =>
                                                            n === index
                                                                ? {
                                                                      ...l,
                                                                      quantity:
                                                                          e
                                                                              .target
                                                                              .value,
                                                                  }
                                                                : l,
                                                    ),
                                                )
                                            }
                                        />
                                        <InputError
                                            message={
                                                errors[
                                                    'lines.' +
                                                        index +
                                                        '.quantity'
                                                ]
                                            }
                                        />
                                    </label>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        disabled={saving}
                                        onClick={() =>
                                            form.setData(
                                                'lines',
                                                form.data.lines.filter(
                                                    (l) =>
                                                        l.item_id !==
                                                        line.item_id,
                                                ),
                                            )
                                        }
                                    >
                                        Remove
                                    </Button>
                                </div>
                            );
                        })
                    ) : (
                        <EmptyState>
                            No items yet. A draft may be saved empty, but
                            submission requires at least one item.
                        </EmptyState>
                    )}
                </section>
                {Object.entries(errors)
                    .filter(([k]) => !k.endsWith('.quantity'))
                    .map(([key, error]) => (
                        <InputError key={key} message={error} />
                    ))}
                <div className="flex flex-wrap gap-3">
                    <Button type="submit" variant="outline" disabled={saving}>
                        Save draft
                    </Button>
                    <Button
                        type="button"
                        disabled={saving}
                        onClick={() => save(true)}
                    >
                        {saving ? 'Saving…' : 'Save and submit for approval'}
                    </Button>
                    <Link
                        className="self-center underline"
                        href={
                            stockRequest
                                ? '/stock-requests/' + stockRequest.id
                                : '/stock-requests'
                        }
                    >
                        Cancel editing
                    </Link>
                </div>
            </form>
        </OrganizationPage>
    );
}
RequestForm.layout = {
    breadcrumbs: [
        { title: 'Stock requests', href: '/stock-requests' },
        { title: 'Draft editor', href: '#' },
    ],
};
