import { useForm } from '@inertiajs/react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { ConfirmChange, selectClass } from '@/components/organization';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import type { Person, StorageArea } from '@/types/organization';

export default function AssignmentManager({
    area,
    employees,
}: {
    area: StorageArea;
    employees: Person[];
}) {
    const current = area.current_assignment;
    const form = useForm({
        user_id: '',
        expected_assignment_id: current?.id ?? null,
    });
    const [mode, setMode] = useState<'assign' | 'end' | null>(null);
    const errors = form.errors as Record<string, string>;
    const selected = employees.find(
        (person) => String(person.id) === form.data.user_id,
    );
    function save() {
        form.transform((data) => ({
            ...data,
            user_id: mode === 'end' ? null : Number(data.user_id),
        }));
        form.put(`/storage-areas/${area.id}/assignment`, {
            preserveScroll: true,
            onSuccess: () => {
                setMode(null);
                form.reset('user_id');
            },
            onError: () => setMode(null),
        });
    }
    return (
        <section
            className="space-y-4 rounded-xl border p-6"
            aria-labelledby="responsibility-heading"
        >
            <h2 id="responsibility-heading" className="text-lg font-semibold">
                Manage responsibility
            </h2>
            <p className="text-sm leading-6 text-muted-foreground">
                Only employees without an active assignment are eligible. To
                move an employee from another area, explicitly end their
                existing assignment first.
            </p>
            {area.is_active ? (
                <form
                    onSubmit={(event) => {
                        event.preventDefault();
                        setMode('assign');
                    }}
                    className="flex flex-wrap items-end gap-3"
                >
                    <div className="min-w-52 flex-1 space-y-2">
                        <Label htmlFor="employee">Stock employee</Label>
                        <select
                            id="employee"
                            required
                            className={selectClass}
                            value={form.data.user_id}
                            onChange={(event) =>
                                form.setData('user_id', event.target.value)
                            }
                        >
                            <option value="">
                                Select an unassigned employee
                            </option>
                            {employees.map((person) => (
                                <option key={person.id} value={person.id}>
                                    {person.name} ({person.email})
                                </option>
                            ))}
                        </select>
                    </div>
                    <Button
                        disabled={form.processing || !form.data.user_id}
                        type="submit"
                    >
                        {current
                            ? 'Reassign responsibility'
                            : 'Assign employee'}
                    </Button>
                </form>
            ) : (
                <p className="text-sm text-muted-foreground">
                    This area is inactive. Reactivate it before assigning a new
                    employee.
                </p>
            )}
            {area.is_active && employees.length === 0 && (
                <p className="text-sm text-muted-foreground">
                    No unassigned stock employees are available. Create an
                    account under User Management or end an existing assignment.
                </p>
            )}
            <InputError message={errors.user_id} />
            <InputError message={errors.assignment} />
            <InputError message={errors.expected_assignment_id} />
            {current && (
                <Button
                    type="button"
                    variant="outline"
                    disabled={form.processing}
                    onClick={() => setMode('end')}
                >
                    End current assignment
                </Button>
            )}
            <ConfirmChange
                open={mode !== null}
                onOpenChange={(open) => {
                    if (!open) setMode(null);
                }}
                title={
                    mode === 'end'
                        ? 'End responsibility?'
                        : 'Confirm responsibility change'
                }
                description={
                    mode === 'end'
                        ? `End ${current?.user?.name}'s responsibility for ${area.name}? The area will be unassigned and their history will be retained.`
                        : `Assign ${selected?.name} to ${area.name}?${current ? ' The current assignment for ' + current.user?.name + ' will end.' : ''} Previous assignments will remain in the history.`
                }
                onConfirm={save}
                processing={form.processing}
            />
        </section>
    );
}
