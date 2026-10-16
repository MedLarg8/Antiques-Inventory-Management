import { Link } from '@inertiajs/react';
import { DataTable, EmptyState, Pagination } from '@/components/organization';
import type { Balances } from '@/types/inventory';
export default function InventoryBalances({
    balances,
    unit,
}: {
    balances: Balances;
    unit?: string;
}) {
    return (
        <section className="space-y-4">
            <h2 className="text-lg font-semibold">Recorded inventory</h2>
            <p className="text-sm text-muted-foreground">
                Uninitialized records do not confirm physical availability.
                Stock changes require a future authorized operation.
            </p>
            {balances.data.length ? (
                <>
                    <DataTable
                        headers={[
                            'Item / storage area',
                            'Category',
                            'Recorded quantity',
                            'Minimum',
                            'Status',
                        ]}
                    >
                        {balances.data.map((b) => (
                            <tr key={b.id}>
                                <td>
                                    {b.item ? (
                                        <Link
                                            className="underline"
                                            href={'/inventory/' + b.item.id}
                                        >
                                            {b.item.name}
                                        </Link>
                                    ) : (
                                        b.storage_area?.name
                                    )}
                                </td>
                                <td>
                                    {b.item?.category.name ??
                                        b.storage_area?.city.name}
                                </td>
                                <td>
                                    {b.quantity === null
                                        ? 'Not initialized'
                                        : b.quantity +
                                          ' ' +
                                          (b.item?.unit_of_measure ??
                                              unit ??
                                              '')}
                                </td>
                                <td>
                                    {b.minimum_quantity ?? 'Not configured'}
                                </td>
                                <td>
                                    {b.status === 'low' ? (
                                        <strong className="text-destructive">
                                            Below minimum
                                        </strong>
                                    ) : b.status === 'uninitialized' ? (
                                        'Not initialized'
                                    ) : (
                                        'Recorded'
                                    )}
                                </td>
                            </tr>
                        ))}
                    </DataTable>
                    <Pagination page={balances} />
                </>
            ) : (
                <EmptyState>
                    No inventory records configured. Physical stock has not been
                    initialized.
                </EmptyState>
            )}
        </section>
    );
}
