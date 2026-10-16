import { Link, useForm } from '@inertiajs/react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import {
    OrganizationPage,
    DataTable,
    Pagination,
    ConfirmChange,
    displayDate,
} from '@/components/organization';
import StockMovementTable from '@/components/stock-movement-table';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import type {
    StockRequest,
    StockEvent,
    StockAvailability,
    Movement,
} from '@/types/stock';
import type { Paginated } from '@/types/organization';
export default function Show({
    stockRequest: r,
    events,
    movements,
    availability,
    statuses,
    actions,
    executionIssues,
}: {
    stockRequest: StockRequest;
    events: Paginated<StockEvent>;
    movements: Movement[];
    availability: Record<number, StockAvailability> | null;
    statuses: Record<string, string>;
    actions: {
        create: boolean;
        edit: boolean;
        cancel: boolean;
        review: boolean;
        execute: boolean;
    };
    executionIssues: string[];
}) {
    const form = useForm({ rejection_reason: '' });
    const [confirm, setConfirm] = useState<
        'submit' | 'approve' | 'reject' | 'cancel' | 'execute' | null
    >(null);
    const errors = form.errors as Record<string, string>;
    function transition() {
        if (!confirm) return;
        form.transform((data) =>
            confirm === 'reject'
                ? { rejection_reason: data.rejection_reason }
                : confirm === 'execute'
                  ? { confirmed: true }
                  : {},
        );
        form.post('/stock-requests/' + r.id + '/' + confirm, {
            preserveScroll: true,
            onSuccess: () => setConfirm(null),
            onError: () => setConfirm(null),
        });
    }
    const summaries = {
        submit: 'Submit this exact request for central approval? Its operation, items and quantities become fixed. Stock is not reserved.',
        approve:
            'Approve this request as written? This authorizes future execution and does not update inventory.',
        reject: 'Reject this request with the entered reason? The employee must create a corrected new draft.',
        cancel: 'Cancel this draft? It becomes terminal and stock stays unchanged.',
        execute:
            'Confirm that the approved physical ' +
            (r.operation_type === 'entry' ? 'receipt' : 'departure') +
            ' has been executed for ' +
            r.storage_area.name +
            '. The exact item quantities below will be recorded together. This confirmation cannot be repeated.',
    };
    return (
        <OrganizationPage
            title={r.request_number}
            description={
                (r.operation_type === 'entry' ? 'Stock entry' : 'Stock exit') +
                ' · ' +
                r.storage_area.city.name +
                ' / ' +
                r.storage_area.name
            }
            actions={<Badge variant="outline">{statuses[r.status]}</Badge>}
        >
            <div className="flex flex-wrap gap-3">
                <Link className="underline" href="/stock-requests">
                    Request list
                </Link>
                {actions.edit && (
                    <Button variant="outline" asChild>
                        <Link href={'/stock-requests/' + r.id + '/edit'}>
                            Edit draft
                        </Link>
                    </Button>
                )}
                {actions.edit && (
                    <Button
                        disabled={form.processing}
                        onClick={() => setConfirm('submit')}
                    >
                        Submit for approval
                    </Button>
                )}
                {actions.cancel && (
                    <Button
                        variant="outline"
                        disabled={form.processing}
                        onClick={() => setConfirm('cancel')}
                    >
                        Cancel draft
                    </Button>
                )}
            </div>
            <dl className="grid gap-5 rounded border p-6 sm:grid-cols-3">
                {Object.entries({
                    'Requested by': r.requester.name,
                    Created: displayDate(r.created_at),
                    Submitted: displayDate(r.submitted_at),
                    'Reviewed by': r.reviewer?.name,
                    Reviewed: displayDate(r.reviewed_at),
                    'Executed by': r.executor?.name,
                    Executed: displayDate(r.executed_at),
                    Reason: r.reason,
                    'Source / destination / reference': r.source_or_destination,
                }).map(([key, value]) => (
                    <div key={key}>
                        <dt className="text-sm text-muted-foreground">{key}</dt>
                        <dd className="whitespace-pre-wrap">
                            {value || 'Not recorded'}
                        </dd>
                    </div>
                ))}
            </dl>
            {r.rejection_reason && (
                <p className="rounded border border-destructive/40 p-5">
                    <strong>Rejection reason: </strong>
                    {r.rejection_reason}{' '}
                    {actions.create && (
                        <Link
                            className="underline"
                            href="/stock-requests/create"
                        >
                            Create a corrected new draft
                        </Link>
                    )}
                </p>
            )}
            <section className="space-y-4">
                <h2 className="text-lg font-semibold">Requested items</h2>
                <p className="text-sm text-muted-foreground">
                    References, names and units are preserved from submission.
                    Availability is current recorded stock, without
                    reservations.
                </p>
                <DataTable
                    headers={[
                        'Item / reference',
                        'Requested quantity',
                        'Current recorded stock',
                    ]}
                >
                    {r.lines.map((line) => (
                        <tr key={line.id}>
                            <td>
                                {line.item_name_snapshot}
                                <p className="text-xs text-muted-foreground">
                                    {line.item_reference_snapshot}
                                </p>
                            </td>
                            <td>
                                {line.quantity} {line.unit_snapshot}
                            </td>
                            <td>
                                {availability === null
                                    ? 'Outside your current depot responsibility'
                                    : availability[line.item_id]?.quantity ===
                                            null || !availability[line.item_id]
                                      ? 'Not initialized'
                                      : availability[line.item_id].quantity +
                                        ' ' +
                                        line.unit_snapshot}
                            </td>
                        </tr>
                    ))}
                </DataTable>
            </section>
            {executionIssues.length > 0 &&
                ['draft', 'pending_approval', 'approved'].includes(
                    r.status,
                ) && (
                    <div
                        className="space-y-2 rounded border border-amber-500/50 p-5"
                        role="note"
                    >
                        <h2 className="font-semibold">
                            Execution is blocked by recorded availability
                        </h2>
                        {executionIssues.map((issue) => (
                            <p className="text-sm" key={issue}>
                                {issue}
                            </p>
                        ))}
                        <p className="text-sm">
                            Resolve these conditions before performing the
                            physical operation. Approval does not override this
                            check.
                        </p>
                    </div>
                )}
            {actions.review && (
                <section className="space-y-4 rounded border p-6">
                    <h2 className="text-lg font-semibold">
                        Administrative decision
                    </h2>
                    <p className="text-sm text-muted-foreground">
                        Approve the requested content as written or reject it
                        with a reason. Approval does not change stock.
                    </p>
                    <Button
                        disabled={form.processing}
                        onClick={() => setConfirm('approve')}
                    >
                        Approve request
                    </Button>
                    <label className="block space-y-2">
                        <span>Rejection reason</span>
                        <textarea
                            className="min-h-24 w-full rounded border p-3"
                            maxLength={10000}
                            value={form.data.rejection_reason}
                            onChange={(e) =>
                                form.setData('rejection_reason', e.target.value)
                            }
                        />
                    </label>
                    <Button
                        variant="destructive"
                        disabled={
                            form.processing ||
                            !form.data.rejection_reason.trim()
                        }
                        onClick={() => setConfirm('reject')}
                    >
                        Reject request
                    </Button>
                </section>
            )}
            {actions.execute && (
                <section className="space-y-4 rounded border p-6">
                    <h2 className="text-lg font-semibold">
                        Confirm physical execution
                    </h2>
                    <p>
                        Approved by {r.reviewer?.name} on{' '}
                        {displayDate(r.reviewed_at)}.
                    </p>
                    <p className="text-sm text-muted-foreground">
                        Review the depot, operation and exact quantities above.
                        Confirm only after the authorized physical operation has
                        been performed. Availability is checked again when you
                        confirm.
                    </p>
                    <Button
                        disabled={form.processing || executionIssues.length > 0}
                        onClick={() => setConfirm('execute')}
                    >
                        Confirm execution…
                    </Button>
                </section>
            )}
            {r.status === 'completed' && (
                <p className="rounded border p-5 font-medium">
                    Completed. The actual executing employee, quantities and
                    movement history are recorded. This request cannot execute
                    again.
                </p>
            )}
            {Object.values(errors).map((error) => (
                <InputError key={error} message={error} />
            ))}
            <section className="space-y-4">
                <h2 className="text-lg font-semibold">Request timeline</h2>
                <ol className="space-y-3">
                    {events.data.map((event) => (
                        <li key={event.id} className="rounded border p-4">
                            <p className="font-medium">
                                {event.event_type.replaceAll('_', ' ')} ·{' '}
                                {event.actor.name}
                            </p>
                            <p className="text-sm text-muted-foreground">
                                {displayDate(event.created_at)} ·{' '}
                                {event.previous_status
                                    ? statuses[event.previous_status] + ' → '
                                    : ''}
                                {statuses[event.new_status]}
                            </p>
                            {event.comment && (
                                <p className="mt-2 whitespace-pre-wrap text-sm">
                                    {event.comment}
                                </p>
                            )}
                        </li>
                    ))}
                </ol>
                <Pagination page={events} />
            </section>
            {movements.length > 0 && (
                <section className="space-y-4">
                    <h2 className="text-lg font-semibold">
                        Executed movements for this request
                    </h2>
                    <StockMovementTable
                        movements={movements.map((m) => ({
                            ...m,
                            stock_request: {
                                id: r.id,
                                request_number: r.request_number,
                            },
                            storage_area: r.storage_area,
                        }))}
                    />
                </section>
            )}
            <ConfirmChange
                open={confirm !== null}
                onOpenChange={(open) => {
                    if (!open) setConfirm(null);
                }}
                title={
                    confirm === 'execute'
                        ? 'Record this physical execution?'
                        : 'Confirm request action'
                }
                description={
                    (confirm ? summaries[confirm] : '') +
                    (confirm === 'execute'
                        ? ' Items: ' +
                          r.lines
                              .map(
                                  (l) =>
                                      l.item_name_snapshot +
                                      ' — ' +
                                      l.quantity +
                                      ' ' +
                                      l.unit_snapshot,
                              )
                              .join('; ')
                        : '')
                }
                onConfirm={transition}
                processing={form.processing}
            />
        </OrganizationPage>
    );
}
Show.layout = {
    breadcrumbs: [
        { title: 'Stock requests', href: '/stock-requests' },
        { title: 'Request details', href: '#' },
    ],
};
