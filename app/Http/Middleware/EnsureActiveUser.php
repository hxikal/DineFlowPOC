<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureActiveUser
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user() ? User::find($request->user()->getKey()) : null;
        if (! $user || $user->status !== 'active' || ! in_array($user->role, ['admin', 'user'], true) || (int) $request->session()->get('auth_version', $user->auth_version) !== $user->auth_version) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors(['username' => __('admin.session_expired')]);
        }
        Auth::setUser($user);
        if ($request->session()->get('auth_role') !== $user->role) {
            $request->session()->regenerate();
        }
        $request->session()->put(['auth_role' => $user->role, 'auth_version' => $user->auth_version]);

        return $next($request);
    }
}
