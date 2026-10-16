import { Link, useForm } from '@inertiajs/react';
import InputError from '@/components/input-error';
import { selectClass } from '@/components/organization';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import type { Employee } from '@/types/employees';
export default function EmployeeProfileForm({
    employee,
    roles,
}: {
    employee?: Partial<Employee>;
    roles?: Record<string, string>;
}) {
    const form = useForm({
        name: employee?.name ?? '',
        email: employee?.email ?? '',
        phone: employee?.phone ?? '',
        administrative_notes: employee?.administrative_notes ?? '',
        ...(roles ? { role: 'stock_employee', is_active: true } : {}),
    });
    return (
        <form
            className="max-w-2xl space-y-5 rounded-lg border p-6"
            onSubmit={(e) => {
                e.preventDefault();
                if (employee?.id) form.put('/users/' + employee.id);
                else form.post('/users');
            }}
        >
            {(['name', 'email', 'phone'] as const).map((k) => (
                <div key={k} className="space-y-2">
                    <label htmlFor={k} className="text-sm font-medium">
                        {k === 'name'
                            ? 'Full name'
                            : k === 'email'
                              ? 'Work email'
                              : 'Phone (optional)'}
                    </label>
                    <Input
                        id={k}
                        type={
                            k === 'email'
                                ? 'email'
                                : k === 'phone'
                                  ? 'tel'
                                  : 'text'
                        }
                        required={k !== 'phone'}
                        value={form.data[k]}
                        maxLength={k === 'phone' ? 40 : 255}
                        onChange={(e) => form.setData(k, e.target.value)}
                    />
                    <InputError message={form.errors[k]} />
                </div>
            ))}
            <label className="block space-y-2">
                <span className="text-sm font-medium">
                    Administrative notes (administrators only)
                </span>
                <textarea
                    className="min-h-28 w-full rounded border p-3"
                    maxLength={10000}
                    value={form.data.administrative_notes}
                    onChange={(e) =>
                        form.setData('administrative_notes', e.target.value)
                    }
                />
                <InputError message={form.errors.administrative_notes} />
            </label>
            {roles && (
                <>
                    <label className="block space-y-2">
                        <span>Initial role</span>
                        <select
                            className={selectClass}
                            value={form.data.role}
                            onChange={(e) =>
                                form.setData('role', e.target.value)
                            }
                        >
                            {Object.entries(roles).map(([v, l]) => (
                                <option key={v} value={v}>
                                    {l}
                                </option>
                            ))}
                        </select>
                    </label>
                    <InputError message={form.errors.role} />
                    <label className="flex gap-2">
                        <input
                            type="checkbox"
                            checked={form.data.is_active}
                            onChange={(e) =>
                                form.setData('is_active', e.target.checked)
                            }
                        />
                        Active account
                    </label>
                    <p className="text-sm text-muted-foreground">
                        Employees choose their own password through an expiring
                        setup link. After creating the account, open its details
                        for secure onboarding. Depot responsibility is assigned
                        separately.
                    </p>
                </>
            )}
            {employee && (
                <p className="text-sm text-muted-foreground">
                    Changing email clears verification and revokes existing
                    sessions. Roles, account access and depot responsibility are
                    managed separately on employee details.
                </p>
            )}
            <div className="flex gap-3">
                <Button disabled={form.processing}>
                    {form.processing
                        ? 'Saving…'
                        : employee
                          ? 'Save profile'
                          : 'Create employee'}
                </Button>
                <Link
                    className="self-center underline"
                    href={employee?.id ? '/users/' + employee.id : '/users'}
                >
                    Cancel
                </Link>
            </div>
        </form>
    );
}
