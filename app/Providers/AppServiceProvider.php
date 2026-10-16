<?php

namespace App\Providers;

use App\Enums\Role;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Auth\EloquentUserProvider;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        Gate::define('access-administration', fn (User $user): bool => $user->is_active && $user->role === Role::Admin);
        Gate::define('view-own-storage', fn (User $user): bool => $user->is_active && $user->role === Role::StockEmployee);
        Auth::provider('active-eloquent', function ($app, array $config) {
            return (new EloquentUserProvider($app['hash'], $config['model']))
                ->withQuery(fn ($query) => $query->where('is_active', true));
        });
        Event::listen(Login::class, function ($event): void {
            if (! $event->user instanceof User) {
                return;
            }
            $user = User::findOrFail($event->user->id);
            if (! $user->is_active || (request()->session()->has('login.auth_version') && request()->session()->get('login.auth_version') !== $user->auth_version)) {
                Auth::guard('web')->logout();
                request()->session()->invalidate();
                throw ValidationException::withMessages(['email' => __('auth.failed')]);
            }
            request()->session()->put('auth_version', $user->auth_version);
            request()->session()->forget('login.auth_version');
            $user->forceFill(['last_login_at' => now()])->save();
        });
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
