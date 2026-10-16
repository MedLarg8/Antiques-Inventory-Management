<?php

namespace App\Http\Controllers\Settings;

use App\Actions\ManageEmployee;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\ProfileDeleteRequest;
use App\Http\Requests\Settings\ProfileUpdateRequest;
use App\Models\EmployeeAudit;
use App\Models\ItemImage;
use App\Models\StockRequest;
use App\Models\StockRequestEvent;
use App\Models\User;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    /**
     * Show the user's profile settings page.
     */
    public function edit(Request $request): Response
    {
        return Inertia::render('settings/profile', [
            'mustVerifyEmail' => $request->user() instanceof MustVerifyEmail,
            'status' => $request->session()->get('status'),
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $request->user()->fill($request->validated());

        if ($request->user()->isDirty('email')) {
            $request->user()->email_verified_at = null;
        }

        $request->user()->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Profile updated.')]);

        return to_route('profile.edit');
    }

    /**
     * Delete the user's profile.
     */
    public function destroy(ProfileDeleteRequest $request): RedirectResponse
    {
        $user = $request->user();

        DB::transaction(function () use ($user): void {
            ManageEmployee::lock();
            $lockedUser = User::query()->lockForUpdate()->findOrFail($user->id);
            if (EmployeeAudit::where('actor_id', $user->id)->orWhere('employee_id', $user->id)->exists()) {
                throw ValidationException::withMessages(['password' => 'This account has administrative history and cannot be deleted.']);
            }
            if (StockRequestEvent::where('actor_id', $user->id)->exists() || StockRequest::where('requested_by', $user->id)->orWhere('reviewed_by', $user->id)->orWhere('executed_by', $user->id)->exists()) {
                throw ValidationException::withMessages(['password' => 'This account has stock-operation history and cannot be deleted.']);
            }
            if ($lockedUser->storageAreaAssignments()->exists() || ItemImage::withTrashed()->where('uploaded_by', $lockedUser->id)->exists()) {
                throw ValidationException::withMessages(['password' => 'This account has assignment history or inventory photographs and cannot be deleted. Contact an administrator.']);
            }
            ManageEmployee::ensureAdministratorRemains($lockedUser);
            $lockedUser->delete();
        });

        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
