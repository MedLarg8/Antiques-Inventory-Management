<?php

namespace App\Http\Controllers;

use App\Actions\ManageEmployee;
use App\Enums\Role;
use App\Http\Requests\EmployeeIndexRequest;
use App\Http\Requests\SaveEmployeeRequest;
use App\Models\City;
use App\Models\EmployeeAudit;
use App\Models\StorageArea;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class EmployeeController extends Controller
{
    public function index(EmployeeIndexRequest $request): Response
    {
        $filters = $request->validated();
        $query = User::select(['id', 'name', 'email', 'role', 'is_active', 'email_verified_at', 'created_at', 'last_login_at'])->with('currentStorageArea.city:id,name');
        if ($search = $filters['search'] ?? null) {
            $query->where(fn ($q) => $q->whereLike('name', "%{$search}%")->orWhereLike('email', "%{$search}%"));
        }
        if ($request->filled('role')) {
            $query->where('role', $filters['role']);
        }
        if ($request->filled('status')) {
            $query->where('is_active', $filters['status'] === 'active');
        }
        if ($request->filled('assignment')) {
            if ($filters['assignment'] === 'assigned') {
                $query->has('currentStorageArea');
            } else {
                $query->doesntHave('currentStorageArea');
            }
        }
        foreach (['city_id', 'storage_area_id'] as $key) {
            if ($request->filled($key)) {
                $query->whereHas('currentStorageArea', fn ($q) => $q->where($key === 'city_id' ? 'city_id' : 'storage_areas.id', $filters[$key]));
            }
        }

        return Inertia::render('users/index', [
            'users' => $query->orderBy($filters['sort'] ?? 'name', $filters['direction'] ?? 'asc')->orderBy('id')->paginate(15)->withQueryString(),
            'filters' => $filters, 'roles' => $this->roles(), 'cities' => City::orderBy('name')->get(['id', 'name']),
            'areas' => StorageArea::orderBy('name')->get(['id', 'name', 'city_id']),
        ]);
    }

    /** @return array<string,string> */
    private function roles(): array
    {
        return collect(Role::cases())->mapWithKeys(fn (Role $role) => [$role->value => $role->label()])->all();
    }

    public function create(): Response
    {
        return Inertia::render('users/create', ['roles' => $this->roles()]);
    }

    public function store(SaveEmployeeRequest $request, ManageEmployee $action): RedirectResponse
    {
        try {
            $user = $action->create($request->user(), $request->validated());
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['email' => 'An account already uses this email address.']);
        }
        if ($user->is_active && $this->mailAvailable()) {
            return $this->sendInvitation($request, $user);
        }
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Account created. Open employee details to generate a private setup link or send email once mail is configured.']);

        return to_route('users.index');
    }

    public function edit(User $user): Response
    {
        return Inertia::render('users/edit', ['employee' => $user->only(['id', 'name', 'email', 'phone', 'administrative_notes'])]);
    }

    public function update(SaveEmployeeRequest $request, User $user, ManageEmployee $action): RedirectResponse
    {
        try {
            $action->profile($request->user(), $user, $request->validated());
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['email' => 'An account already uses this email address.']);
        }
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Profile saved. An email change requires verification and a fresh sign-in.']);

        return to_route('users.show', $user);
    }

    public function show(User $user): Response
    {
        return Inertia::render('users/show', [
            'employee' => [...$user->only(['id', 'name', 'email', 'phone', 'is_active', 'role', 'email_verified_at', 'created_at', 'last_login_at']), 'administrative_notes' => $user->administrative_notes],
            'current' => $user->storageAreaAssignments()->whereNull('ended_at')->with('storageArea.city')->first(),
            'history' => $user->storageAreaAssignments()->whereNotNull('ended_at')->with('storageArea.city')->latest('id')->paginate(10, ['*'], 'history_page')->withQueryString(),
            'audits' => EmployeeAudit::where('employee_id', $user->id)->with('actor:id,name')->latest('id')->paginate(10, ['*'], 'audit_page')->withQueryString(),
            'areas' => StorageArea::with(['city:id,name', 'currentAssignment.user:id,name'])->orderBy('name')->get(),
            'roles' => $this->roles(), 'mailAvailable' => $this->mailAvailable(),
            'eligibleForAssignment' => $user->is_active && $user->role === Role::StockEmployee,
            'capabilities' => $user->role->capabilities(),
        ]);
    }

    public function role(SaveEmployeeRequest $request, User $user, ManageEmployee $action): RedirectResponse
    {
        $action->access($request->user(), $user, Role::from($request->validated('role')), null);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Role updated. Existing sessions have been revoked.']);

        return back();
    }

    public function status(SaveEmployeeRequest $request, User $user, ManageEmployee $action): RedirectResponse
    {
        $action->access($request->user(), $user, null, $request->boolean('is_active'));
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Account status updated. Historical assignments were preserved.']);

        return back();
    }

    public function invite(SaveEmployeeRequest $request, User $user): RedirectResponse
    {
        return $this->sendInvitation($request, $user);
    }

    private function mailAvailable(): bool
    {
        return User::authenticationMailAvailable();
    }

    private function sendInvitation(Request $request, User $user): RedirectResponse
    {
        if (! $this->mailAvailable()) {
            throw ValidationException::withMessages(['onboarding' => 'Configure a real mail transport or generate a private setup link. Log mail is disabled for authentication links.']);
        }
        try {
            DB::transaction(function () use ($request, $user) {
                ManageEmployee::lock();
                ManageEmployee::authorizeActor($request->user());
                $target = User::lockForUpdate()->findOrFail($user->id);
                if (! $target->is_active) {
                    throw ValidationException::withMessages(['onboarding' => 'Reactivate this account before initiating a password reset.']);
                }
                $status = Password::sendResetLink(['email' => $target->email]);
                if ($status !== Password::ResetLinkSent) {
                    throw ValidationException::withMessages(['onboarding' => __($status)]);
                }
                ManageEmployee::audit($request->user(), $target, 'password_reset_initiated', ['delivery' => 'email']);
            }, 3);
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable) {
            throw ValidationException::withMessages(['onboarding' => 'The email could not be sent. Check mail configuration or use a private setup link.']);
        }
        Inertia::flash('toast', ['type' => 'success', 'message' => 'A secure password-setup/reset email has been requested.']);

        return to_route('users.index');
    }

    public function setupLink(SaveEmployeeRequest $request, User $user): Response
    {
        Inertia::encryptHistory();
        $links = DB::transaction(function () use ($request, $user) {
            ManageEmployee::lock();
            ManageEmployee::authorizeActor($request->user());
            $target = User::lockForUpdate()->findOrFail($user->id);
            if (! $target->is_active) {
                throw ValidationException::withMessages(['onboarding' => 'Reactivate this account first.']);
            }
            $broker = Password::broker();
            if ($broker->getRepository()->recentlyCreatedToken($target)) {
                throw ValidationException::withMessages(['onboarding' => 'A setup link was recently generated. Wait one minute before retrying.']);
            }
            $token = $broker->createToken($target);
            ManageEmployee::audit($request->user(), $target, 'password_reset_initiated', ['delivery' => 'private_handoff']);

            return ['setupUrl' => url(route('password.reset', ['token' => $token, 'email' => $target->email], false)),
                'verificationUrl' => $target->hasVerifiedEmail() ? null : URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), ['id' => $target->id, 'hash' => sha1($target->getEmailForVerification())])];
        }, 3);

        return Inertia::render('users/setup-link', [...$links, 'employee' => $user->only(['id', 'name', 'email']), 'expiresMinutes' => config('auth.passwords.users.expire')]);
    }
}
