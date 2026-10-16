import { useForm } from '@inertiajs/react';
import { useState } from 'react';
import { ConfirmChange, EmptyState } from '@/components/organization';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import type { Photo } from '@/types/inventory';
function PhotoCard({ photo, canManage }: { photo: Photo; canManage: boolean }) {
    const form = useForm({
        caption: photo.caption ?? '',
        display_order: photo.display_order,
        is_primary: photo.is_primary,
    });
    const [removing, setRemoving] = useState(false);
    return (
        <article className="space-y-3 rounded-lg border p-4">
            <a href={photo.preview_url} target="_blank" rel="noreferrer">
                <img
                    className="h-52 w-full rounded object-contain"
                    src={photo.preview_url}
                    alt={photo.caption || 'Inventory photograph'}
                    loading="lazy"
                />
            </a>
            {photo.is_primary && (
                <p className="text-sm font-medium">Primary photograph</p>
            )}
            <a className="text-sm underline" href={photo.original_url}>
                Download original
            </a>
            {canManage ? (
                <form
                    className="space-y-3"
                    onSubmit={(e) => {
                        e.preventDefault();
                        form.patch('/item-images/' + photo.id, {
                            preserveScroll: true,
                        });
                    }}
                >
                    <label className="block text-sm">
                        Caption
                        <Input
                            value={form.data.caption}
                            maxLength={500}
                            onChange={(e) =>
                                form.setData('caption', e.target.value)
                            }
                        />
                    </label>
                    <label className="block text-sm">
                        Display order (lower first)
                        <Input
                            type="number"
                            min={0}
                            value={form.data.display_order}
                            onChange={(e) =>
                                form.setData(
                                    'display_order',
                                    Number(e.target.value),
                                )
                            }
                        />
                    </label>
                    <label className="flex gap-2 text-sm">
                        <input
                            type="checkbox"
                            checked={form.data.is_primary}
                            onChange={(e) =>
                                form.setData('is_primary', e.target.checked)
                            }
                        />
                        Primary photograph
                    </label>
                    {Object.values(form.errors).map((e) => (
                        <InputError key={e} message={e} />
                    ))}
                    <div className="flex gap-2">
                        <Button variant="outline" disabled={form.processing}>
                            Save details
                        </Button>
                        <Button
                            type="button"
                            variant="destructive"
                            onClick={() => setRemoving(true)}
                        >
                            Remove
                        </Button>
                    </div>
                    <ConfirmChange
                        open={removing}
                        onOpenChange={setRemoving}
                        title="Remove photograph?"
                        description="The original and generated previews will be removed."
                        processing={form.processing}
                        onConfirm={() =>
                            form.delete('/item-images/' + photo.id, {
                                preserveScroll: true,
                                onSuccess: () => setRemoving(false),
                            })
                        }
                    />
                </form>
            ) : (
                <p className="text-sm">{photo.caption}</p>
            )}
        </article>
    );
}
export default function InventoryImages({
    images,
    kind,
    record,
    canManage,
}: {
    images: Photo[];
    kind: 'items' | 'assets';
    record: number;
    canManage: boolean;
}) {
    const form = useForm<{ image: File | null }>({ image: null });
    return (
        <section className="space-y-4">
            <h2 className="text-lg font-semibold">Photographs</h2>
            {canManage && (
                <form
                    className="flex flex-wrap items-end gap-3 rounded border p-4"
                    onSubmit={(e) => {
                        e.preventDefault();
                        form.post(
                            '/catalog/' + kind + '/' + record + '/images',
                            {
                                forceFormData: true,
                                preserveScroll: true,
                                onSuccess: () => form.reset(),
                            },
                        );
                    }}
                >
                    <label className="space-y-2 text-sm">
                        JPEG, PNG or WebP
                        <Input
                            key={form.data.image ? 'selected' : 'empty'}
                            type="file"
                            accept="image/jpeg,image/png,image/webp"
                            onChange={(e) =>
                                form.setData(
                                    'image',
                                    e.target.files?.[0] ?? null,
                                )
                            }
                        />
                    </label>
                    <Button disabled={form.processing || !form.data.image}>
                        Upload photograph
                    </Button>
                    <InputError message={form.errors.image} />
                </form>
            )}
            {images.length ? (
                <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                    {images.map((p) => (
                        <PhotoCard
                            key={
                                p.id +
                                '-' +
                                p.is_primary +
                                '-' +
                                p.display_order
                            }
                            photo={p}
                            canManage={canManage}
                        />
                    ))}
                </div>
            ) : (
                <EmptyState>No photographs uploaded.</EmptyState>
            )}
        </section>
    );
}
