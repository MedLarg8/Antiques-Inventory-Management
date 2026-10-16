import { Link } from '@inertiajs/react';
import { DataTable, EmptyState, displayDate } from '@/components/organization';
import type { Movement } from '@/types/stock';
export default function StockMovementTable({
    movements,
}: {
    movements: Movement[];
}) {
    return movements.length ? (
        <DataTable
            headers={[
                'Request / depot',
                'Item',
                'Change',
                'Before',
                'After',
                'Executed by / date',
            ]}
        >
            {movements.map((m) => (
                <tr key={m.id}>
                    <td>
                        {m.canViewRequest ? (
                            <Link
                                className="underline"
                                href={'/stock-requests/' + m.stock_request_id}
                            >
                                {m.stock_request.request_number}
                            </Link>
                        ) : (
                            m.stock_request.request_number
                        )}
                        <p className="text-xs text-muted-foreground">
                            {m.storage_area.name}
                        </p>
                    </td>
                    <td>
                        {m.line.item_name_snapshot}
                        <p className="text-xs text-muted-foreground">
                            {m.line.item_reference_snapshot}
                        </p>
                    </td>
                    <td>
                        {m.movement_type === 'entry' ? '+' : ''}
                        {m.quantity_delta} {m.line.unit_snapshot}
                    </td>
                    <td>{m.quantity_before}</td>
                    <td>{m.quantity_after}</td>
                    <td>
                        {m.executor.name}
                        <p className="text-xs text-muted-foreground">
                            {displayDate(m.executed_at)}
                        </p>
                    </td>
                </tr>
            ))}
        </DataTable>
    ) : (
        <EmptyState>
            No executed movements in this scope. Approval alone creates no
            movements.
        </EmptyState>
    );
}
