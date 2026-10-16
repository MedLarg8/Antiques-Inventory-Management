import { Link, useForm } from '@inertiajs/react';
import {
    OrganizationPage,
    DataTable,
    Pagination,
    EmptyState,
    StatusBadge,
} from '@/components/organization';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import type { Category } from '@/types/inventory';
import type { Paginated } from '@/types/organization';
export default function Categories({
    categories,
    filters,
    canManage,
}: {
    categories: Paginated<Category>;
    filters: { search?: string };
    canManage: boolean;
}) {
    const form = useForm({ search: filters.search ?? '' });
    return (
        <OrganizationPage
            title="Item categories"
            description="Categories describe items independently of their tracking mode. Parent relationships form the category hierarchy."
            actions={
                canManage && (
                    <Button asChild>
                        <Link href="/catalog/categories/create">
                            Create category
                        </Link>
                    </Button>
                )
            }
        >
            <Link href="/inventory" className="underline">
                Inventory catalog
            </Link>
            <form
                className="flex gap-3"
                onSubmit={(e) => {
                    e.preventDefault();
                    form.get('/categories');
                }}
            >
                <Input
                    aria-label="Search categories"
                    value={form.data.search}
                    onChange={(e) => form.setData('search', e.target.value)}
                />
                <Button>Search</Button>
            </form>
            {categories.data.length ? (
                <DataTable
                    headers={[
                        'Name',
                        'Code',
                        'Parent category',
                        'Status',
                        'Actions',
                    ]}
                >
                    {categories.data.map((c) => (
                        <tr key={c.id}>
                            <td>{c.name}</td>
                            <td>{c.code}</td>
                            <td>{c.parent?.name ?? 'Root category'}</td>
                            <td>
                                <StatusBadge active={c.is_active} />
                            </td>
                            <td>
                                {canManage && (
                                    <Link
                                        className="underline"
                                        href={
                                            '/catalog/categories/' +
                                            c.id +
                                            '/edit'
                                        }
                                    >
                                        Edit
                                    </Link>
                                )}
                            </td>
                        </tr>
                    ))}
                </DataTable>
            ) : (
                <EmptyState>No categories found.</EmptyState>
            )}
            <Pagination page={categories} />
        </OrganizationPage>
    );
}
Categories.layout = {
    breadcrumbs: [{ title: 'Categories', href: '/categories' }],
};
