<?php

namespace App\Actions\Fortify;

use App\Actions\ManageEmployee;
use App\Concerns\PasswordValidationRules;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\ResetsUserPasswords;

class ResetUserPassword implements ResetsUserPasswords
{
    use PasswordValidationRules;

    /**
     * Validate and reset the user's forgotten password.
     *
     * @param  array<string, string>  $input
     */
    public function reset(User $user, array $input): void
    {
        Validator::make($input, [
            'password' => $this->passwordRules(),
        ])->validate();

        DB::transaction(function () use ($user, $input): void {
            $locked = User::lockForUpdate()->findOrFail($user->id);
            if (! $locked->is_active) {
                throw ValidationException::withMessages(['email' => 'This account cannot reset its password.']);
            }
            $user->refresh();
            $user->forceFill(['password' => $input['password']]);
            ManageEmployee::revoke($user);
        });
    }
}
