<?php

namespace App\Actions;

use App\Enums\Role;
use App\Models\EmployeeAudit;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ManageEmployee
{
    // Shared by all administrative role/status writers and account deletion.
    public static function lock(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::select('SELECT pg_advisory_xact_lock(4162026)');
        } else {
            User::where('role', Role::Admin)->orderBy('id')->lockForUpdate()->get();
        }
    }

    public static function ensureAdministratorRemains(User $target): void
    {
        if ($target->is_active && $target->role === Role::Admin && ! User::where('role', Role::Admin)->where('is_active', true)->whereKeyNot($target->id)->exists()) {
            throw ValidationException::withMessages(['account' => 'The last active administrator must remain active with the administrator role.']);
        }
    }

    public static function revoke(User $user): void
    {
        $user->auth_version++;
        $user->remember_token = null;
        $user->save();
        Password::deleteToken($user);
        if (config('session.driver') === 'database') {
            DB::connection(config('session.connection'))->table(config('session.table', 'sessions'))->where('user_id', $user->id)->delete();
        }
    }

    /** @param array<string,mixed> $details */
    public static function audit(User $actor, User $employee, string $action, array $details = []): void
    {
        EmployeeAudit::create(['actor_id' => $actor->id, 'employee_id' => $employee->id, 'action' => $action, 'details' => $details]);
    }

    public static function authorizeActor(User $actor): void
    {
        $fresh = User::findOrFail($actor->id);
        abort_unless($fresh->can('access-administration'), 403);
    }

    /** @param array<string,mixed> $data */
    public function create(User $actor, array $data): User
    {
        return DB::transaction(function () use ($actor, $data) {
            self::lock();
            self::authorizeActor($actor);
            $user = new User;
            $user->forceFill([...$data, 'role' => $data['role'] ?? Role::StockEmployee, 'is_active' => $data['is_active'] ?? true, 'password' => Str::password(64)]);
            $user->save();
            self::audit($actor, $user, 'employee_created', ['role' => $user->role->value, 'is_active' => $user->is_active]);

            return $user;
        }, 3);
    }

    /** @param array<string,mixed> $data */
    public function profile(User $actor, User $target, array $data): void
    {
        DB::transaction(function () use ($actor, $target, $data) {
            self::lock();
            self::authorizeActor($actor);
            $user = User::lockForUpdate()->findOrFail($target->id);
            $changed = array_keys(array_filter($data, fn ($value, $key) => $user->getAttribute($key) !== $value, ARRAY_FILTER_USE_BOTH));
            $emailChanged = $user->email !== $data['email'];
            $oldEmail = $user->email;
            $user->forceFill($data);
            if ($emailChanged) {
                $user->email_verified_at = null;
                DB::table('password_reset_tokens')->where('email', $oldEmail)->delete();
                self::revoke($user);
            } else {
                $user->save();
            }
            if ($changed) {
                self::audit($actor, $user, 'profile_updated', ['fields' => $changed, 'email_verification_cleared' => $emailChanged]);
            }
        }, 3);
    }

    public function access(User $actor, User $target, ?Role $role, ?bool $active): void
    {
        DB::transaction(function () use ($actor, $target, $role, $active) {
            self::lock();
            self::authorizeActor($actor);
            $user = User::lockForUpdate()->findOrFail($target->id);
            $newRole = $role ?? $user->role;
            $newActive = $active ?? $user->is_active;
            if ($active === false && $actor->id === $user->id) {
                throw ValidationException::withMessages(['account' => 'You cannot deactivate your own account.']);
            }
            if ($newRole === $user->role && $newActive === $user->is_active) {
                return;
            }
            if (! $newActive || $newRole !== Role::Admin) {
                self::ensureAdministratorRemains($user);
            }
            if ((! $newActive || $newRole !== Role::StockEmployee) && $user->storageAreaAssignments()->whereNull('ended_at')->exists()) {
                throw ValidationException::withMessages(['account' => 'End the current depot assignment before changing this role or deactivating the account.']);
            }
            $before = ['role' => $user->role->value, 'is_active' => $user->is_active];
            $user->role = $newRole;
            $user->is_active = $newActive;
            self::revoke($user);
            self::audit($actor, $user, $role !== null ? 'role_changed' : ($newActive ? 'employee_activated' : 'employee_deactivated'), ['before' => $before, 'after' => ['role' => $newRole->value, 'is_active' => $newActive]]);
        }, 3);
    }
}
