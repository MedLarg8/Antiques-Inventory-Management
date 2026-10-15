<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Http\Requests\CreateStockEmployeeRequest;
use App\Http\Requests\OrganizationIndexRequest;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class EmployeeController extends Controller
{
    public function index(OrganizationIndexRequest $request): Response
    {
        $search = $request->string('search')->toString();

        return Inertia::render('users/index', [
            'users' => User::query()->select(['id', 'name', 'email', 'role', 'email_verified_at'])
                ->with('currentStorageArea:id,name,code')
                ->when($search !== '', fn ($q) => $q->where(fn ($q) => $q->whereLike('name', '%'.$search.'%')->orWhereLike('email', '%'.$search.'%')))
                ->orderBy('name')->orderBy('id')->paginate(15)->withQueryString(),
            'filters' => ['search' => $search],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('users/create');
    }

    public function store(CreateStockEmployeeRequest $request): RedirectResponse
    {
        try {
            $user = new User($request->validated());
            $user->role = Role::StockEmployee;
            $user->password = Str::password(64);
            $user->save();
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['email' => 'An account already uses this email address.']);
        }

        return $this->sendInvitation($user);
    }

    public function invite(User $user): RedirectResponse
    {
        abort_unless($user->role === Role::StockEmployee, 403);

        return $this->sendInvitation($user);
    }

    private function sendInvitation(User $user): RedirectResponse
    {
        try {
            $status = Password::sendResetLink(['email' => $user->email]);
            $success = $status === Password::ResetLinkSent;
            Inertia::flash('toast', ['type' => $success ? 'success' : 'error', 'message' => $success
                ? 'Account saved. A password-setup email has been requested.'
                : 'Account saved. '.__($status).' Use Send setup link to retry.']);
        } catch (Throwable $exception) {
            report($exception);
            Inertia::flash('toast', ['type' => 'error', 'message' => 'Account saved, but the setup email could not be sent. Check mail configuration and retry Send setup link.']);
        }

        return to_route('users.index');
    }
}
