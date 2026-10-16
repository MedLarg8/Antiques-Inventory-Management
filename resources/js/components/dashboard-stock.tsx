import { Link } from '@inertiajs/react';
import { Card, CardHeader, CardTitle, CardContent } from '@/components/ui/card';
import { DataTable, EmptyState, displayDate } from '@/components/organization';
import StockMovementTable from '@/components/stock-movement-table';
import type { StockSummary } from '@/types/stock';
export default function DashboardStock({ stock }: { stock: StockSummary }) {
    return (
        <section className="space-y-6">
            <h2 className="text-lg font-semibold">
                Stock requests and execution
            </h2>
            <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                {stock.metrics.map((m) => (
                    <Card key={m.label}>
                        <CardHeader>
                            <CardTitle className="text-sm">
                                <Link className="underline" href={m.href}>
                                    {m.label}
                                </Link>
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="text-3xl font-semibold">
                            {m.value}
                        </CardContent>
                    </Card>
                ))}
            </div>
            <h3 className="font-semibold">Recently completed requests</h3>
            {stock.recentCompleted.length ? (
                <DataTable headers={['Request', 'Depot', 'Executed']}>
                    {stock.recentCompleted.map((r) => (
                        <tr key={r.id}>
                            <td>
                                <Link
                                    className="underline"
                                    href={'/stock-requests/' + r.id}
                                >
                                    {r.request_number}
                                </Link>
                            </td>
                            <td>{r.storage_area.name}</td>
                            <td>{displayDate(r.executed_at)}</td>
                        </tr>
                    ))}
                </DataTable>
            ) : (
                <EmptyState>No completed requests in this scope.</EmptyState>
            )}
            <h3 className="font-semibold">Recent executed movements</h3>
            <StockMovementTable movements={stock.recentMovements} />
            <Link className="inline-block underline" href="/stock-movements">
                View movement history
            </Link>
        </section>
    );
}
