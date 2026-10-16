<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureAccountAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->isMethod('POST') && $request->routeIs('login.store')) {
            $candidate = is_string($request->input('email')) ? User::where('email', $request->input('email'))->first() : null;
            $request->session()->put('login.auth_version', $candidate->auth_version ?? 0);
        }
        if ($request->session()->has('login.id')) {
            $candidate = User::whereKey($request->session()->get('login.id'))->first();
            if (! $candidate || ! $candidate->is_active || $request->session()->get('login.auth_version', 0) !== $candidate->auth_version) {
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return redirect()->route('login')->withErrors(['email' => 'Your account access changed. Sign in again.']);
            }
        }
        $user = $request->user();
        if ($user instanceof User) {
            $fresh = User::find($user->id);
            $version = $request->session()->get('auth_version', 0);
            if (! $fresh || ! $fresh->is_active || $version !== $fresh->auth_version) {
                Auth::guard('web')->logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return $request->expectsJson() ? response()->json(['message' => 'Your account access changed. Sign in again.'], 401) : redirect()->route('login')->withErrors(['email' => 'Your account access changed. Sign in again.']);
            }
            // Use database permissions, including when a long-lived guard cached a user.
            Auth::guard('web')->setUser($fresh);
        }
        $response = $next($request);
        if ($request->user() || $request->is('reset-password*', 'email/verify*')) {
            $response->headers->set('Cache-Control', 'private, no-store');
            $response->headers->set('Referrer-Policy', 'no-referrer');
        }

        return $response;
    }
}
