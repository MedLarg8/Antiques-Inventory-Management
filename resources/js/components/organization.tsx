import { Head, Link, useForm } from '@inertiajs/react';
import type { FormEvent, ReactNode } from 'react';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { City, Paginated } from '@/types/organization';

export function OrganizationPage({
    title,
    description,
    actions,
    children,
}: {
    title: string;
    description: string;
    actions?: ReactNode;
    children: ReactNode;
}) {
    return (
        <>
            <Head title={title} />
            <div className="flex flex-1 flex-col gap-6 p-4 sm:p-6 lg:p-8">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight">
                            {title}
                        </h1>
                        <p className="mt-2 max-w-2xl text-sm leading-6 text-muted-foreground">
                            {description}
                        </p>
                    </div>
                    {actions}
                </div>
                {children}
            </div>
        </>
    );
}

export function StatusBadge({ active }: { active: boolean }) {
    return (
        <Badge variant={active ? 'secondary' : 'outline'}>
            {active ? 'Active' : 'Inactive'}
        </Badge>
    );
}

export function EmptyState({ children }: { children: ReactNode }) {
    return (
        <div className="rounded-lg border border-dashed p-10 text-center text-sm leading-6 text-muted-foreground">
            {children}
        </div>
    );
}

export function DataTable({
    headers,
    children,
}: {
    headers: string[];
    children: ReactNode;
}) {
    return (
        <div className="overflow-x-auto rounded-lg border">
            <table className="w-full text-left text-sm">
                <thead className="border-b bg-muted/50">
                    <tr>
                        {headers.map((header) => (
                            <th
                                key={header}
                                scope="col"
                                className="whitespace-nowrap px-4 py-3 font-medium"
                            >
                                {header}
                            </th>
                        ))}
                    </tr>
                </thead>
                <tbody className="[&_td]:px-4 [&_td]:py-4 [&_tr]:border-b [&_tr:last-child]:border-0">
                    {children}
                </tbody>
            </table>
        </div>
    );
}

export function Pagination({
    page,
}: {
    page: Omit<Paginated<unknown>, 'data'>;
}) {
    return (
        <nav
            aria-label="Pagination"
            className="flex flex-wrap items-center justify-between gap-3 text-sm"
        >
            <span className="text-muted-foreground">
                {page.total} records · Page {page.current_page} of{' '}
                {page.last_page}
            </span>
            <div className="flex gap-2">
                {page.prev_page_url ? (
                    <Button variant="outline" size="sm" asChild>
                        <Link href={page.prev_page_url} preserveScroll>
                            Previous
                        </Link>
                    </Button>
                ) : (
                    <Button variant="outline" size="sm" disabled>
                        Previous
                    </Button>
                )}
                {page.next_page_url ? (
                    <Button variant="outline" size="sm" asChild>
                        <Link href={page.next_page_url} preserveScroll>
                            Next
                        </Link>
                    </Button>
                ) : (
                    <Button variant="outline" size="sm" disabled>
                        Next
                    </Button>
                )}
            </div>
        </nav>
    );
}

export const selectClass =
    'h-9 w-full rounded-md border border-input bg-background px-3 text-sm shadow-xs focus-visible:outline-2 focus-visible:outline-ring';

export function SearchFilters({
    url,
    filters,
    cities,
}: {
    url: string;
    filters: { search?: string; city_id?: string; status?: string };
    cities?: City[];
}) {
    const form = useForm({
        search: filters.search ?? '',
        city_id: String(filters.city_id ?? ''),
        status: filters.status ?? '',
    });
    function submit(event: FormEvent) {
        event.preventDefault();
        form.get(url, { preserveState: true, replace: true });
    }
    return (
        <form
            onSubmit={submit}
            className="flex flex-wrap items-end gap-3 rounded-lg border bg-muted/20 p-4"
        >
            <div className="min-w-48 flex-1 space-y-2">
                <Label htmlFor="search">Search</Label>
                <Input
                    id="search"
                    name="search"
                    value={form.data.search}
                    maxLength={100}
                    onChange={(event) =>
                        form.setData('search', event.target.value)
                    }
                    placeholder="Search by name or code"
                />
                <InputError message={form.errors.search} />
            </div>
            {cities && (
                <>
                    <div className="min-w-44 space-y-2">
                        <Label htmlFor="city-filter">City</Label>
                        <select
                            id="city-filter"
                            className={selectClass}
                            value={form.data.city_id}
                            onChange={(e) =>
                                form.setData('city_id', e.target.value)
                            }
                        >
                            <option value="">All cities</option>
                            {cities.map((city) => (
                                <option key={city.id} value={city.id}>
                                    {city.name}
                                </option>
                            ))}
                        </select>
                        <InputError message={form.errors.city_id} />
                    </div>
                    <div className="min-w-36 space-y-2">
                        <Label htmlFor="status-filter">Status</Label>
                        <select
                            id="status-filter"
                            className={selectClass}
                            value={form.data.status}
                            onChange={(e) =>
                                form.setData('status', e.target.value)
                            }
                        >
                            <option value="">All statuses</option>
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                </>
            )}
            <Button type="submit" disabled={form.processing}>
                {form.processing ? 'Searching…' : 'Apply'}
            </Button>
            <Button variant="ghost" asChild>
                <Link href={url}>Clear</Link>
            </Button>
        </form>
    );
}

export function ConfirmChange({
    open,
    onOpenChange,
    title,
    description,
    onConfirm,
    processing,
}: {
    open: boolean;
    onOpenChange: (value: boolean) => void;
    title: string;
    description: string;
    onConfirm: () => void;
    processing: boolean;
}) {
    return (
        <Dialog
            open={open}
            onOpenChange={(value) => {
                if (!processing) onOpenChange(value);
            }}
        >
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>{title}</DialogTitle>
                    <DialogDescription>{description}</DialogDescription>
                </DialogHeader>
                <DialogFooter>
                    <Button
                        variant="outline"
                        disabled={processing}
                        onClick={() => onOpenChange(false)}
                    >
                        Cancel
                    </Button>
                    <Button disabled={processing} onClick={onConfirm}>
                        {processing ? 'Saving…' : 'Confirm change'}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

export function displayDate(value: string | null | undefined): string {
    return value
        ? new Intl.DateTimeFormat(undefined, {
              dateStyle: 'medium',
              timeStyle: 'short',
          }).format(new Date(value))
        : '—';
}
