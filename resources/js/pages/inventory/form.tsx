import { Link, useForm } from '@inertiajs/react';
import { OrganizationPage, selectClass } from '@/components/organization';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import InputError from '@/components/input-error';
import type { Asset, Category, Item } from '@/types/inventory';
import { modes } from '@/types/inventory';
export default function CatalogForm({
    kind,
    record,
    parent,
    categories,
    units,
}: {
    kind: 'items' | 'categories' | 'assets';
    record: (Item | Category | Asset) | null;
    parent: Item | null;
    categories: Category[];
    units: string[];
}) {
    const initial: Record<string, string | boolean> = {};
    const fields =
        kind === 'items'
            ? [
                  'name',
                  'reference_code',
                  'category_id',
                  'tracking_mode',
                  'unit_of_measure',
                  'description',
                  'brand',
                  'model',
                  'notes',
              ]
            : kind === 'categories'
              ? ['name', 'code', 'parent_id', 'description']
              : [
                    'asset_reference',
                    'serial_number',
                    'condition_notes',
                    'description',
                ];
    const values = record as unknown as Record<
        string,
        string | boolean | number | null
    > | null;
    fields.forEach(
        (k) =>
            (initial[k] = String(
                values?.[k] ??
                    (k === 'tracking_mode'
                        ? 'quantity_consumable'
                        : k === 'unit_of_measure'
                          ? 'piece'
                          : ''),
            )),
    );
    if (kind !== 'assets')
        initial.is_active =
            values?.is_active === undefined ? true : Boolean(values.is_active);
    const form = useForm(initial);
    const labels: Record<string, string> = {
        name: 'Name',
        reference_code: 'Reference',
        category_id: 'Category',
        tracking_mode: 'Tracking mode',
        unit_of_measure: 'Unit of measure',
        description: 'Description',
        brand: 'Brand',
        model: 'Model',
        notes: 'Notes',
        code: 'Code',
        parent_id: 'Parent category',
        asset_reference: 'Asset reference',
        serial_number: 'Serial number',
        condition_notes: 'Condition notes',
    };
    function categoryLabel(c: Category): string {
        const names = [c.name];
        let p = c.parent_id;
        const seen = new Set([c.id]);
        while (p && !seen.has(p)) {
            seen.add(p);
            const ancestor = categories.find((a) => a.id === p);
            if (!ancestor) break;
            names.unshift(ancestor.name);
            p = ancestor.parent_id;
        }
        return names.join(' / ') + (c.is_active ? '' : ' (inactive)');
    }
    return (
        <OrganizationPage
            title={
                (record ? 'Edit ' : 'Create ') +
                (kind === 'categories'
                    ? 'category'
                    : kind === 'assets'
                      ? 'asset'
                      : 'item')
            }
            description={
                parent
                    ? 'Parent item: ' +
                      parent.name +
                      ' — physical location is not assigned.'
                    : 'Manage catalog metadata. Physical stock quantities cannot be edited here.'
            }
        >
            <form
                className="max-w-2xl space-y-5"
                onSubmit={(e) => {
                    e.preventDefault();
                    const url =
                        '/catalog/' +
                        kind +
                        (record ? '/' + record.id : '') +
                        (parent ? '?item=' + parent.id : '');
                    if (record) form.put(url);
                    else form.post(url);
                }}
            >
                {fields.map((k) => {
                    const options =
                        k === 'tracking_mode'
                            ? Object.entries(modes)
                            : k === 'unit_of_measure'
                              ? units.map((u) => [u, u])
                              : ['category_id', 'parent_id'].includes(k)
                                ? categories
                                      .filter(
                                          (c) =>
                                              k !== 'parent_id' ||
                                              c.id !== record?.id,
                                      )
                                      .map((c) => [
                                          String(c.id),
                                          categoryLabel(c),
                                      ])
                                : null;
                    return (
                        <div key={k} className="space-y-2">
                            <label htmlFor={k} className="text-sm font-medium">
                                {labels[k]}
                            </label>
                            {options ? (
                                <select
                                    id={k}
                                    className={selectClass}
                                    value={String(form.data[k])}
                                    onChange={(e) =>
                                        form.setData(k, e.target.value)
                                    }
                                >
                                    <option value="">
                                        {k === 'parent_id'
                                            ? 'Root category'
                                            : 'Select…'}
                                    </option>
                                    {options.map(([v, l]) => (
                                        <option key={v} value={v}>
                                            {l}
                                        </option>
                                    ))}
                                </select>
                            ) : [
                                  'description',
                                  'notes',
                                  'condition_notes',
                              ].includes(k) ? (
                                <textarea
                                    id={k}
                                    className="min-h-28 w-full rounded border p-3"
                                    value={String(form.data[k])}
                                    onChange={(e) =>
                                        form.setData(k, e.target.value)
                                    }
                                />
                            ) : (
                                <Input
                                    id={k}
                                    value={String(form.data[k])}
                                    onChange={(e) =>
                                        form.setData(k, e.target.value)
                                    }
                                />
                            )}
                            <InputError message={form.errors[k]} />
                        </div>
                    );
                })}
                {kind !== 'assets' && (
                    <label className="flex gap-2">
                        <input
                            type="checkbox"
                            checked={Boolean(form.data.is_active)}
                            onChange={(e) =>
                                form.setData('is_active', e.target.checked)
                            }
                        />
                        Active
                    </label>
                )}
                <div className="flex items-center gap-4">
                    <Button disabled={form.processing}>Save</Button>
                    <Link
                        href={
                            kind === 'categories'
                                ? '/categories'
                                : parent
                                  ? '/inventory/' + parent.id
                                  : '/inventory'
                        }
                    >
                        Cancel
                    </Link>
                </div>
            </form>
        </OrganizationPage>
    );
}
CatalogForm.layout = { breadcrumbs: [{ title: 'Catalog editor', href: '#' }] };
