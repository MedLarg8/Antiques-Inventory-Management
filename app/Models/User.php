<?php

namespace App\Models;

use App\Enums\Role;
use Database\Factories\UserFactory;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;

/**
 * @property Role $role
 * @property bool $is_active
 * @property int $auth_version
 * @property Carbon|null $last_login_at
 * @property int $id
 * @property string $name
 * @property string $email
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token', 'auth_version', 'administrative_notes'])]
class User extends Authenticatable implements MustVerifyEmail, PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable;

    protected $attributes = ['is_active' => true, 'auth_version' => 0];

    public static function authenticationMailAvailable(): bool
    {
        return app()->environment('testing') || in_array(config('mail.mailers.'.config('mail.default').'.transport'), ['smtp', 'sendmail', 'ses', 'postmark', 'resend'], true);
    }

    public function sendPasswordResetNotification($token): void
    {
        if (self::authenticationMailAvailable()) {
            $this->notify(new ResetPassword($token));
        }
    }

    public function sendEmailVerificationNotification(): void
    {
        if (self::authenticationMailAvailable()) {
            $this->notify(new VerifyEmail);
        }
    }

    /** @return HasMany<StorageAreaAssignment, $this> */
    public function storageAreaAssignments(): HasMany
    {
        return $this->hasMany(StorageAreaAssignment::class);
    }

    /** @return HasOneThrough<StorageArea, StorageAreaAssignment, $this> */
    public function currentStorageArea(): HasOneThrough
    {
        return $this->hasOneThrough(StorageArea::class, StorageAreaAssignment::class, 'user_id', 'id', 'id', 'storage_area_id')
            ->whereNull('storage_area_assignments.ended_at');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'role' => Role::class,
            'is_active' => 'boolean',
            'auth_version' => 'integer',
            'last_login_at' => 'datetime',
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            /* @chisel-2fa */
            'two_factor_confirmed_at' => 'datetime',
            /* @end-chisel-2fa */
        ];
    }
}
