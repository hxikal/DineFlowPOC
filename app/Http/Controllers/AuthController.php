<?php

namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use App\Http\Requests\PasswordRequest;
use App\Models\User;
use App\Services\AuditWriter;
use App\Services\UserManager;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function create(): View
    {
        return view('auth.login');
    }

    public function store(LoginRequest $request, AuditWriter $audit): RedirectResponse
    {
        $key = 'login:'.hash('sha256', Str::lower($request->string('username')->toString()).'|'.$request->ip());
        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages(['username' => __('admin.throttled')]);
        }
        RateLimiter::hit($key, 60);
        $user = User::where('username', $request->input('username'))->first();
        $valid = Hash::check($request->input('password'), $user?->password ?? Hash::make(Str::random(40)));
        if (! $valid || ! $user || $user->status !== 'active' || ! in_array($user->role, ['admin', 'user'], true)) {
            throw ValidationException::withMessages(['username' => __('admin.login_failed')]);
        }
        $user = DB::transaction(function () use ($user, $audit) {
            $fresh = User::whereKey($user->getKey())->lockForUpdate()->firstOrFail();
            if ($fresh->status !== 'active' || $fresh->password !== $user->password || ! in_array($fresh->role, ['admin', 'user'], true)) {
                throw ValidationException::withMessages(['username' => __('admin.login_failed')]);
            }
            $fresh->last_login_at = now();
            $fresh->save();
            $audit->record($fresh, 'LOGIN', 'authentication', null);

            return $fresh;
        });
        RateLimiter::clear($key);
        Auth::login($user);
        $request->session()->regenerate();
        $request->session()->put(['auth_version' => $user->auth_version, 'auth_role' => $user->role, 'locale' => $user->preferred_locale]);

        return redirect()->intended(route('workspace'));
    }

    public function destroy(Request $request, AuditWriter $audit): RedirectResponse
    {
        $audit->record($request->user(), 'LOGOUT', 'authentication', null);
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    public function password(): View
    {
        return view('account.password');
    }

    public function updatePassword(PasswordRequest $request, UserManager $users): RedirectResponse
    {
        $user = $users->changePassword($request->user(), $request->input('current_password'), $request->input('password'));
        $request->session()->regenerate();
        $request->session()->put('auth_version', $user->auth_version);

        return back()->with('success', __('admin.saved'));
    }
}
