import { Link, router } from '@inertiajs/react';
import { useState } from 'react';
import {
    ConfirmChange,
    DataTable,
    EmptyState,
    OrganizationPage,
    Pagination,
    SearchFilters,
} from '@/components/organization';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import type { User } from '@/types';
import type { Paginated, StorageArea } from '@/types/organization';

type Account = Pick<
    User,
    'id' | 'name' | 'email' | 'role' | 'email_verified_at'
> & { current_storage_area: StorageArea | null };
export default function Users({
    users,
    filters,
}: {
    users: Paginated<Account>;
    filters: { search?: string };
}) {
    const [selected, setSelected] = useState<Account | null>(null);
    const [processing, setProcessing] = useState(false);
    function send() {
        if (!selected) return;
        router.post(
            `/users/${selected.id}/invite`,
            {},
            {
                preserveScroll: true,
                onStart: () => setProcessing(true),
                onFinish: () => {
                    setProcessing(false);
                    setSelected(null);
                },
            },
        );
    }
    return (
        <OrganizationPage
            title="User Management"
            description="Create stock employee accounts and review their role, email verification and current responsibility."
            actions={
                <Button asChild>
                    <Link href="/users/create">Create stock employee</Link>
                </Button>
            }
        >
            <SearchFilters url="/users" filters={filters} />
            {users.data.length ? (
                <DataTable
                    headers={[
                        'Name / email',
                        'Role',
                        'Account status',
                        'Storage area',
                        'Actions',
                    ]}
                >
                    {users.data.map((user) => (
                        <tr key={user.id}>
                            <td className="font-medium">
                                {user.name}
                                <div className="mt-1 text-xs font-normal text-muted-foreground">
                                    {user.email}
                                </div>
                            </td>
                            <td>
                                {user.role === 'admin'
                                    ? 'Administrator'
                                    : 'Stock Employee'}
                            </td>
                            <td>
                                <Badge variant="outline">
                                    {user.email_verified_at
                                        ? 'Email verified'
                                        : 'Awaiting email verification'}
                                </Badge>
                            </td>
                            <td>
                                {user.current_storage_area ? (
                                    <Link
                                        className="underline underline-offset-4"
                                        href={`/storage-areas/${user.current_storage_area.id}`}
                                    >
                                        {user.current_storage_area.name}
                                    </Link>
                                ) : (
                                    'Unassigned'
                                )}
                            </td>
                            <td>
                                {user.role === 'stock_employee' && (
                                    <Button
                                        size="sm"
                                        variant="outline"
                                        onClick={() => setSelected(user)}
                                    >
                                        Send setup link
                                        <span className="sr-only">
                                            {' '}
                                            to {user.name}
                                        </span>
                                    </Button>
                                )}
                            </td>
                        </tr>
                    ))}
                </DataTable>
            ) : (
                <EmptyState>No users match this search.</EmptyState>
            )}
            <Pagination page={users} />
            <ConfirmChange
                open={selected !== null}
                onOpenChange={(open) => {
                    if (!open) setSelected(null);
                }}
                title="Send password-setup link?"
                description={`Send a secure password-reset link to ${selected?.email}? Existing passwords remain unchanged until the employee chooses a new one.`}
                onConfirm={send}
                processing={processing}
            />
        </OrganizationPage>
    );
}
Users.layout = { breadcrumbs: [{ title: 'User Management', href: '/users' }] };
