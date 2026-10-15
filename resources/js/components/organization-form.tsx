import { Link, useForm } from '@inertiajs/react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import InputError from '@/components/input-error';
import {
    ConfirmChange,
    OrganizationPage,
    selectClass,
} from '@/components/organization';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { City, StorageArea } from '@/types/organization';

export default function OrganizationForm({
    record,
    cities,
    kind,
}: {
    record: City | StorageArea | null;
    cities?: City[];
    kind: 'city' | 'storage area';
}) {
    const isArea = kind === 'storage area';
    const area = isArea ? (record as StorageArea | null) : null;
    const base = isArea ? '/storage-areas' : '/cities';
    const form = useForm({
        name: record?.name ?? '',
        code: record?.code ?? '',
        description: record?.description ?? '',
        is_active: record?.is_active ?? true,
        city_id: String(area?.city_id ?? ''),
        location_details: area?.location_details ?? '',
    });
    const [confirm, setConfirm] = useState(false);
    function save() {
        const options = {
            onSuccess: () => setConfirm(false),
            onError: () => setConfirm(false),
        };
        if (record) form.put(`${base}/${record.id}`, options);
        else form.post(base, options);
    }
    function submit(event: FormEvent) {
        event.preventDefault();
        if (
            record &&
            (record.is_active !== form.data.is_active ||
                (area && String(area.city_id) !== form.data.city_id))
        )
            setConfirm(true);
        else save();
    }
    return (
        <OrganizationPage
            title={`${record ? 'Edit' : 'Create'} ${kind}`}
            description="Maintain the physical organizational structure. Existing records and assignment history are retained."
        >
            <form
                onSubmit={submit}
                className="max-w-2xl space-y-6 rounded-xl border p-6"
            >
                <div className="grid gap-5 sm:grid-cols-2">
                    <div className="space-y-2">
                        <Label htmlFor="name">Name</Label>
                        <Input
                            id="name"
                            name="name"
                            required
                            maxLength={150}
                            value={form.data.name}
                            onChange={(e) =>
                                form.setData('name', e.target.value)
                            }
                            aria-invalid={!!form.errors.name}
                        />
                        <InputError message={form.errors.name} />
                    </div>
                    <div className="space-y-2">
                        <Label htmlFor="code">
                            Code{!isArea && ' (optional)'}
                        </Label>
                        <Input
                            id="code"
                            name="code"
                            required={isArea}
                            maxLength={30}
                            value={form.data.code}
                            onChange={(e) =>
                                form.setData('code', e.target.value)
                            }
                            aria-invalid={!!form.errors.code}
                        />
                        <p className="text-xs text-muted-foreground">
                            Letters, numbers, hyphens and underscores. Saved in
                            uppercase.
                        </p>
                        <InputError message={form.errors.code} />
                    </div>
                </div>
                {isArea && (
                    <div className="space-y-2">
                        <Label htmlFor="city_id">City</Label>
                        <select
                            id="city_id"
                            name="city_id"
                            required
                            className={selectClass}
                            value={form.data.city_id}
                            onChange={(e) =>
                                form.setData('city_id', e.target.value)
                            }
                        >
                            <option value="">Select a city</option>
                            {cities?.map((city) => (
                                <option key={city.id} value={city.id}>
                                    {city.name}
                                    {!city.is_active && ' (inactive)'}
                                </option>
                            ))}
                        </select>
                        <InputError message={form.errors.city_id} />
                        {cities?.length === 0 && (
                            <p className="text-sm text-muted-foreground">
                                Create a city before adding a storage area.{' '}
                                <Link
                                    className="underline"
                                    href="/cities/create"
                                >
                                    Create city
                                </Link>
                            </p>
                        )}
                    </div>
                )}
                {isArea && (
                    <div className="space-y-2">
                        <Label htmlFor="location_details">
                            Location details (optional)
                        </Label>
                        <textarea
                            id="location_details"
                            name="location_details"
                            maxLength={2000}
                            className={selectClass + ' h-24 py-2'}
                            value={form.data.location_details}
                            onChange={(e) =>
                                form.setData('location_details', e.target.value)
                            }
                        />
                        <InputError message={form.errors.location_details} />
                    </div>
                )}
                <div className="space-y-2">
                    <Label htmlFor="description">Description (optional)</Label>
                    <textarea
                        id="description"
                        name="description"
                        maxLength={5000}
                        className={selectClass + ' h-28 py-2'}
                        value={form.data.description}
                        onChange={(e) =>
                            form.setData('description', e.target.value)
                        }
                    />
                    <InputError message={form.errors.description} />
                </div>
                <div className="space-y-2">
                    <Label htmlFor="is_active">Status</Label>
                    <select
                        id="is_active"
                        name="is_active"
                        className={selectClass}
                        value={form.data.is_active ? 'active' : 'inactive'}
                        onChange={(e) =>
                            form.setData(
                                'is_active',
                                e.target.value === 'active',
                            )
                        }
                    >
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                    <p className="text-xs leading-5 text-muted-foreground">
                        {isArea
                            ? 'Deactivation preserves the responsible employee and history, but prevents new assignments.'
                            : 'City status does not automatically change storage areas or assignments.'}
                    </p>
                    <InputError message={form.errors.is_active} />
                </div>
                <div className="flex gap-3">
                    <Button
                        type="submit"
                        disabled={
                            form.processing || (isArea && cities?.length === 0)
                        }
                    >
                        {form.processing ? 'Saving…' : 'Save ' + kind}
                    </Button>
                    <Button variant="outline" asChild>
                        <Link href={base}>Cancel</Link>
                    </Button>
                </div>
            </form>
            <ConfirmChange
                open={confirm}
                onOpenChange={setConfirm}
                title="Confirm organizational change"
                description={`Save changes to ${record?.name}? Status and city changes preserve existing assignments and their history.`}
                onConfirm={save}
                processing={form.processing}
            />
        </OrganizationPage>
    );
}
