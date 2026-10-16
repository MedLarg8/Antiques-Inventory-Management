import { Link, useForm, usePage } from '@inertiajs/react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import {
    OrganizationPage,
    DataTable,
    EmptyState,
    Pagination,
    StatusBadge,
    ConfirmChange,
    displayDate,
    selectClass,
} from '@/components/organization';
import { Button } from '@/components/ui/button';
import type { Employee, Audit, EmployeeAssignment } from '@/types/employees';
import type { Paginated, StorageArea } from '@/types/organization';
export default function Show({
    employee,
    current,
    history,
    audits,
    areas,
    roles,
    mailAvailable,
    eligibleForAssignment,
    capabilities,
}: {
    employee: Employee;
    current: EmployeeAssignment | null;
    history: Paginated<EmployeeAssignment>;
    audits: Paginated<Audit>;
    areas: StorageArea[];
    roles: Record<string, string>;
    mailAvailable: boolean;
    eligibleForAssignment: boolean;
    capabilities: string;
}) {
    const { auth } = usePage().props;
    const role = useForm({ role: employee.role });
    const status = useForm({ is_active: !employee.is_active });
    const assignment = useForm({
        user_id: employee.id,
        expected_assignment_id: null as number | null,
    });
    const onboarding = useForm({});
    const [areaId, setAreaId] = useState('');
    const [confirm, setConfirm] = useState<
        'role' | 'status' | 'assign' | 'end' | 'email' | 'local' | null
    >(null);
    const selectedArea = areas.find((a) => String(a.id) === areaId);
    function activityDetails(audit: Audit): string {
        const details = audit.details;
        if (audit.action === 'employee_created') {
            return `${roles[details.role ?? ''] ?? 'Account'}; ${details.is_active ? 'active' : 'inactive'}.`;
        }
        if (audit.action === 'role_changed') {
            return `${roles[details.before?.role ?? ''] ?? 'Previous role'} to ${roles[details.after?.role ?? ''] ?? 'new role'}.`;
        }
        if (audit.action === 'profile_updated') {
            const labels: Record<string, string> = {
                name: 'name',
                email: 'email',
                phone: 'phone',
                administrative_notes: 'administrative notes',
            };
            return (
                'Updated ' +
                (details.fields ?? [])
                    .map((field) => labels[field] ?? field)
                    .join(', ') +
                (details.email_verification_cleared
                    ? '. Email verification required again.'
                    : '.')
            );
        }
        if (audit.action === 'depot_responsibility_changed') {
            const area = areas.find(
                (candidate) => candidate.id === details.storage_area_id,
            );
            return `${details.new_employee_id === employee.id ? 'Assigned to' : 'Responsibility ended for'} ${area?.name ?? 'the depot'}.`;
        }
        if (audit.action === 'password_reset_initiated')
            return details.delivery === 'email'
                ? 'Setup/reset email requested.'
                : 'Expiring setup link generated for private handoff.';
        return audit.action === 'employee_activated'
            ? 'Account access restored; depot assignment required separately.'
            : 'Account access revoked; account and history preserved.';
    }
    const processing =
        role.processing ||
        status.processing ||
        assignment.processing ||
        onboarding.processing;
    function save() {
        const opts = {
            preserveScroll: true,
            onSuccess: () => setConfirm(null),
            onError: () => setConfirm(null),
        };
        if (confirm === 'role')
            role.put('/users/' + employee.id + '/role', opts);
        else if (confirm === 'status') {
            status.transform(() => ({ is_active: !employee.is_active }));
            status.put('/users/' + employee.id + '/status', opts);
        } else if (confirm === 'email')
            onboarding.post('/users/' + employee.id + '/invite', opts);
        else if (confirm === 'local')
            onboarding.post('/users/' + employee.id + '/setup-link', opts);
        else {
            const ending = confirm === 'end';
            const area = ending ? current?.storage_area : selectedArea;
            if (!area) return;
            assignment.transform(() => ({
                user_id: ending ? null : employee.id,
                expected_assignment_id: ending
                    ? (current?.id ?? null)
                    : (area.current_assignment?.id ?? null),
            }));
            assignment.put('/storage-areas/' + area.id + '/assignment', opts);
        }
    }
    const descriptions = {
        role:
            'Change permissions to ' +
            (roles[role.data.role] ?? role.data.role) +
            '? Existing sessions and remembered sign-ins will be revoked. End any incompatible depot assignment first.',
        status: employee.is_active
            ? 'Deactivate this account and revoke its access? End its current depot assignment first.'
            : 'Reactivate this account? Historical depot responsibility will not be restored.',
        assign:
            'Assign ' +
            employee.name +
            ' to ' +
            selectedArea?.name +
            '? ' +
            (selectedArea?.current_assignment
                ? 'Its current employee’s assignment will end.'
                : '') +
            ' Assignment history will be preserved.',
        end: 'End the current depot responsibility? The depot will become unassigned and history will be preserved.',
        email: 'Send an expiring password setup/reset email? Existing passwords stay unchanged until the employee resets them.',
        local: 'Generate a private expiring setup link? Verify the recipient’s identity and deliver it privately. This invalidates previous reset links.',
    };
    return (
        <OrganizationPage
            title={employee.name}
            description="Employee profile, access permissions and depot responsibility."
            actions={
                <Button asChild variant="outline">
                    <Link href={'/users/' + employee.id + '/edit'}>
                        Edit profile
                    </Link>
                </Button>
            }
        >
            <dl className="grid gap-5 rounded border p-6 sm:grid-cols-3">
                {Object.entries({
                    Email: employee.email,
                    'Email verification': employee.email_verified_at
                        ? 'Verified'
                        : 'Awaiting verification',
                    Phone: employee.phone,
                    Created: displayDate(employee.created_at),
                    'Last successful sign-in': employee.last_login_at
                        ? displayDate(employee.last_login_at)
                        : 'Not recorded',
                    'Administrative notes': employee.administrative_notes,
                }).map(([k, v]) => (
                    <div key={k}>
                        <dt className="text-sm text-muted-foreground">{k}</dt>
                        <dd className="whitespace-pre-wrap">
                            {v || 'Not provided'}
                        </dd>
                    </div>
                ))}
                <div>
                    <dt>Account access</dt>
                    <dd>
                        <StatusBadge active={employee.is_active} />
                    </dd>
                </div>
            </dl>
            <section className="space-y-4 rounded border p-6">
                <h2 className="text-lg font-semibold">Role and permissions</h2>
                <p>{roles[employee.role]}</p>
                <p className="text-sm text-muted-foreground">{capabilities}</p>
                <form
                    className="flex flex-wrap gap-3"
                    onSubmit={(e) => {
                        e.preventDefault();
                        setConfirm('role');
                    }}
                >
                    <label className="min-w-56 flex-1">
                        Application role
                        <select
                            className={selectClass}
                            value={role.data.role}
                            onChange={(e) =>
                                role.setData('role', e.target.value)
                            }
                        >
                            {Object.entries(roles).map(([v, l]) => (
                                <option key={v} value={v}>
                                    {l}
                                </option>
                            ))}
                        </select>
                    </label>
                    <Button
                        className="self-end"
                        disabled={
                            processing || role.data.role === employee.role
                        }
                    >
                        Change role
                    </Button>
                </form>
                <InputError message={role.errors.role} />
            </section>
            <section className="space-y-4 rounded border p-6">
                <h2 className="text-lg font-semibold">Account access</h2>
                <p className="text-sm text-muted-foreground">
                    Deactivation blocks sign-in and revokes existing sessions.
                    The account and its history are preserved.
                </p>
                <Button
                    variant={employee.is_active ? 'destructive' : 'outline'}
                    disabled={
                        processing ||
                        (auth.user.id === employee.id && employee.is_active)
                    }
                    onClick={() => setConfirm('status')}
                >
                    {employee.is_active
                        ? 'Deactivate account'
                        : 'Reactivate account'}
                </Button>
                {auth.user.id === employee.id && (
                    <p className="text-sm text-muted-foreground">
                        You cannot deactivate your own account.
                    </p>
                )}
            </section>
            <section className="space-y-4 rounded border p-6">
                <h2 className="text-lg font-semibold">Depot responsibility</h2>
                {current ? (
                    <>
                        <p>
                            <Link
                                className="underline"
                                href={
                                    '/storage-areas/' + current.storage_area.id
                                }
                            >
                                {current.storage_area.city.name} /{' '}
                                {current.storage_area.name}
                            </Link>{' '}
                            — since {displayDate(current.assigned_at)}
                        </p>
                        <Button
                            variant="outline"
                            disabled={processing}
                            onClick={() => setConfirm('end')}
                        >
                            End assignment
                        </Button>
                        <p className="text-sm text-muted-foreground">
                            End this assignment before assigning another depot,
                            changing to an incompatible role or deactivating the
                            account.
                        </p>
                    </>
                ) : (
                    <>
                        <p>No current depot assignment.</p>
                        {eligibleForAssignment && (
                            <form
                                className="flex flex-wrap gap-3"
                                onSubmit={(e) => {
                                    e.preventDefault();
                                    setConfirm('assign');
                                }}
                            >
                                <label className="min-w-56 flex-1">
                                    Depot
                                    <select
                                        className={selectClass}
                                        value={areaId}
                                        required
                                        onChange={(e) =>
                                            setAreaId(e.target.value)
                                        }
                                    >
                                        <option value="">
                                            Choose an active depot
                                        </option>
                                        {areas
                                            .filter((a) => a.is_active)
                                            .map((a) => (
                                                <option key={a.id} value={a.id}>
                                                    {a.city.name} / {a.name}
                                                    {a.current_assignment
                                                        ? ' (replaces ' +
                                                          a.current_assignment
                                                              .user?.name +
                                                          ')'
                                                        : ''}
                                                </option>
                                            ))}
                                    </select>
                                </label>
                                <Button
                                    className="self-end"
                                    disabled={processing || !areaId}
                                >
                                    Assign responsibility
                                </Button>
                            </form>
                        )}
                    </>
                )}
            </section>
            <section className="space-y-4 rounded border p-6">
                <h2 className="text-lg font-semibold">
                    Password setup and reset
                </h2>
                <p className="text-sm text-muted-foreground">
                    Employees choose their own password. The private handoff
                    also provides a signed verification link for unverified
                    accounts. Setup does not bypass configured two-factor
                    authentication.
                </p>
                <div className="flex flex-wrap gap-3">
                    <Button
                        variant="outline"
                        disabled={
                            processing || !employee.is_active || !mailAvailable
                        }
                        onClick={() => setConfirm('email')}
                    >
                        Send setup/reset email
                    </Button>
                    <Button
                        variant="outline"
                        disabled={processing || !employee.is_active}
                        onClick={() => setConfirm('local')}
                    >
                        Generate private setup link
                    </Button>
                </div>
                {!mailAvailable && (
                    <p className="text-sm text-muted-foreground">
                        Email delivery is unavailable. Use private handoff or
                        configure a real mail transport.
                    </p>
                )}
            </section>
            {[role.errors, status.errors, assignment.errors, onboarding.errors]
                .flatMap((e) => Object.values(e))
                .filter((e): e is string => typeof e === 'string')
                .map((e, i) => (
                    <InputError key={i} message={e} />
                ))}
            <section className="space-y-4">
                <h2 className="text-lg font-semibold">
                    Previous depot assignments
                </h2>
                {history.data.length ? (
                    <DataTable headers={['Depot / city', 'Started', 'Ended']}>
                        {history.data.map((a) => (
                            <tr key={a.id}>
                                <td>
                                    {a.storage_area.city.name} /{' '}
                                    {a.storage_area.name}
                                </td>
                                <td>{displayDate(a.assigned_at)}</td>
                                <td>{displayDate(a.ended_at)}</td>
                            </tr>
                        ))}
                    </DataTable>
                ) : (
                    <EmptyState>No previous assignments.</EmptyState>
                )}
                <Pagination page={history} />
            </section>
            <section className="space-y-4">
                <h2 className="text-lg font-semibold">
                    Administrative activity
                </h2>
                {audits.data.length ? (
                    <DataTable
                        headers={['Action', 'Administrator', 'Time', 'Details']}
                    >
                        {audits.data.map((a) => (
                            <tr key={a.id}>
                                <td>{a.action.replaceAll('_', ' ')}</td>
                                <td>{a.actor.name}</td>
                                <td>{displayDate(a.created_at)}</td>
                                <td>{activityDetails(a)}</td>
                            </tr>
                        ))}
                    </DataTable>
                ) : (
                    <EmptyState>
                        No administrative changes recorded since activity
                        tracking was introduced.
                    </EmptyState>
                )}
                <Pagination page={audits} />
            </section>
            <ConfirmChange
                open={confirm !== null}
                onOpenChange={(v) => {
                    if (!v) setConfirm(null);
                }}
                title="Confirm administrative action"
                description={confirm ? descriptions[confirm] : ''}
                processing={processing}
                onConfirm={save}
            />
        </OrganizationPage>
    );
}
Show.layout = {
    breadcrumbs: [
        { title: 'Employee directory', href: '/users' },
        { title: 'Employee details', href: '#' },
    ],
};
