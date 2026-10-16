import { Link } from '@inertiajs/react';
import {
    OrganizationPage,
    DataTable,
    EmptyState,
    Pagination,
    displayDate,
} from '@/components/organization';
import StockFilters from '@/components/stock-filters';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import type { StockRequestPage } from '@/types/stock';
export default function Requests({
    requests,
    filters,
    canReview,
    canCreate,
    areas,
    cities,
    approval,
    statuses,
}: {
    requests: StockRequestPage;
    filters: Record<string, string>;
    canReview: boolean;
    canCreate: boolean;
    areas: { id: number; name: string }[];
    cities: { id: number; name: string }[];
    approval: boolean;
    statuses: Record<string, string>;
}) {
    return (
        <OrganizationPage
            title={approval ? 'Administrative approval' : 'Stock requests'}
            description="Draft, submit, review and confirm physical execution. Approval authorizes an operation; stock changes only when execution succeeds."
            actions={
                canCreate && (
                    <Button asChild>
                        <Link href="/stock-requests/create">
                            Create request
                        </Link>
                    </Button>
                )
            }
        >
            {!canReview && !canCreate && (
                <p className="rounded border p-4 text-sm text-muted-foreground">
                    An active depot assignment is required to create or submit
                    requests. Your own historical requests remain available.
                </p>
            )}
            <div className="flex flex-wrap gap-4">
                <Link className="underline" href="/stock-requests">
                    Requests
                </Link>
                {canReview && (
                    <Link className="underline" href="/approval-requests">
                        Review pending requests
                    </Link>
                )}
                <Link className="underline" href="/stock-movements">
                    Movement history
                </Link>
            </div>
            <StockFilters
                url={approval ? '/approval-requests' : '/stock-requests'}
                filters={filters}
                admin={canReview}
                areas={areas}
                cities={cities}
                statuses={statuses}
            />
            {requests.data.length ? (
                <DataTable
                    headers={[
                        'Request',
                        'Operation',
                        'Depot / city',
                        'Requester',
                        'Item lines',
                        'Created / submitted',
                        'Status',
                        'Action',
                    ]}
                >
                    {requests.data.map((r) => (
                        <tr key={r.id}>
                            <td>
                                <Link
                                    className="font-medium underline"
                                    href={'/stock-requests/' + r.id}
                                >
                                    {r.request_number}
                                </Link>
                            </td>
                            <td>
                                {r.operation_type === 'entry'
                                    ? 'Stock entry'
                                    : 'Stock exit'}
                            </td>
                            <td>
                                {r.storage_area.name}
                                <p className="text-xs text-muted-foreground">
                                    {r.storage_area.city.name}
                                </p>
                            </td>
                            <td>{r.requester.name}</td>
                            <td>{r.lines_count}</td>
                            <td>
                                {displayDate(r.created_at)}
                                <p className="text-xs text-muted-foreground">
                                    {r.submitted_at
                                        ? 'Submitted ' +
                                          displayDate(r.submitted_at)
                                        : 'Not submitted'}
                                </p>
                            </td>
                            <td>
                                <Badge variant="outline">
                                    {statuses[r.status]}
                                </Badge>
                            </td>
                            <td>
                                <Link
                                    className="underline"
                                    href={'/stock-requests/' + r.id}
                                >
                                    {canReview &&
                                    r.status === 'pending_approval'
                                        ? 'Review'
                                        : 'Details'}
                                </Link>
                            </td>
                        </tr>
                    ))}
                </DataTable>
            ) : (
                <EmptyState>
                    No requests match this scope. Entries and exits support
                    quantity-based items only.
                </EmptyState>
            )}
            <Pagination page={requests} />
        </OrganizationPage>
    );
}
Requests.layout = {
    breadcrumbs: [{ title: 'Stock requests', href: '/stock-requests' }],
};
