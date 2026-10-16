import { Link, useForm } from '@inertiajs/react';
import { selectClass } from '@/components/organization';
import InputError from '@/components/input-error';
import { Input } from '@/components/ui/input';
import { Button } from '@/components/ui/button';
export default function StockFilters({
    url,
    filters,
    admin,
    areas,
    cities,
    statuses,
}: {
    url: string;
    filters: Record<string, string>;
    admin: boolean;
    areas: { id: number; name: string }[];
    cities: { id: number; name: string }[];
    statuses?: Record<string, string>;
}) {
    const form = useForm({
        search: filters.search ?? '',
        status: filters.status ?? '',
        operation_type: filters.operation_type ?? '',
        storage_area_id: filters.storage_area_id ?? '',
        city_id: filters.city_id ?? '',
        employee: filters.employee ?? '',
        from: filters.from ?? '',
        to: filters.to ?? '',
        scope: filters.scope ?? '',
    });
    const choices: Record<string, [string, string][]> = {
        operation_type: [
            ['', 'All operations'],
            ['entry', 'Stock entry'],
            ['exit', 'Stock exit'],
        ],
        ...(statuses
            ? {
                  status: [
                      ['', 'All statuses'],
                      ...Object.entries(statuses),
                  ] as [string, string][],
              }
            : {}),
        ...(admin
            ? {
                  storage_area_id: [
                      ['', 'All depots'],
                      ...areas.map((a) => [String(a.id), a.name]),
                  ] as [string, string][],
                  city_id: [
                      ['', 'All cities'],
                      ...cities.map((c) => [String(c.id), c.name]),
                  ] as [string, string][],
              }
            : statuses
              ? {
                    scope: [
                        ['', 'My requests and depot execution'],
                        ['mine', 'My requests'],
                        ['execution', 'Awaiting execution at my depot'],
                    ] as [string, string][],
                }
              : {}),
    };
    const labels: Record<string, string> = {
        operation_type: 'Operation',
        status: 'Status',
        storage_area_id: 'Depot',
        city_id: 'City',
        scope: 'Request scope',
    };
    return (
        <form
            className="grid gap-4 rounded border p-4 sm:grid-cols-2 xl:grid-cols-4"
            onSubmit={(e) => {
                e.preventDefault();
                form.get(url, { preserveState: true });
            }}
        >
            {statuses && (
                <label className="space-y-2">
                    <span>Request reference</span>
                    <Input
                        value={form.data.search}
                        onChange={(e) => form.setData('search', e.target.value)}
                        maxLength={100}
                    />
                </label>
            )}
            {Object.entries(choices).map(([key, options]) => (
                <label key={key} className="space-y-2">
                    <span>{labels[key]}</span>
                    <select
                        className={selectClass}
                        value={form.data[key as keyof typeof form.data]}
                        onChange={(e) =>
                            form.setData(
                                key as keyof typeof form.data,
                                e.target.value,
                            )
                        }
                    >
                        {options.map(([v, l]) => (
                            <option key={v} value={v}>
                                {l}
                            </option>
                        ))}
                    </select>
                </label>
            ))}
            {admin && statuses && (
                <label className="space-y-2">
                    <span>Requesting employee</span>
                    <Input
                        value={form.data.employee}
                        onChange={(e) =>
                            form.setData('employee', e.target.value)
                        }
                        placeholder="Name or email"
                        maxLength={100}
                    />
                </label>
            )}
            <label className="space-y-2">
                <span>{statuses ? 'Created' : 'Executed'} from</span>
                <Input
                    type="date"
                    value={form.data.from}
                    onChange={(e) => form.setData('from', e.target.value)}
                />
            </label>
            <label className="space-y-2">
                <span>Through</span>
                <Input
                    type="date"
                    value={form.data.to}
                    onChange={(e) => form.setData('to', e.target.value)}
                />
            </label>
            <div className="flex items-end gap-3">
                <Button disabled={form.processing}>Apply filters</Button>
                <Link href={url} className="mb-2 underline">
                    Reset
                </Link>
            </div>
            {Object.values(form.errors).map((e) => (
                <InputError key={e} message={e} />
            ))}
        </form>
    );
}
