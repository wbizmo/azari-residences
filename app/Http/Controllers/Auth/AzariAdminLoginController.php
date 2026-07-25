<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\StaffLoginHistory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AzariAdminLoginController extends Controller
{
    public function create(): View|RedirectResponse
    {
        $user = Auth::user();

        if ($user && ! $user->isStaff()) {
            abort(404);
        }

        if ($user && $user->isStaff() && ! $user->isSuspended()) {
            return redirect()->route('azari.admin.dashboard');
        }

        return view('admin.auth.login');
    }

    public function store(Request $request): RedirectResponse
    {
        if ($request->user() && ! $request->user()->isStaff()) {
            abort(404);
        }

        if ($request->user() && $request->user()->isStaff() && ! $request->user()->isSuspended()) {
            return redirect()->route('azari.admin.dashboard');
        }

        $validated = $request->validate(['login' => ['required', 'string', 'max:255'], 'password' => ['required', 'string']]);
        $field = filter_var($validated['login'], FILTER_VALIDATE_EMAIL) ? 'email' : 'username';
        $key = 'azari-admin-login:'.mb_strtolower($validated['login']).'|'.$request->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages(['login' => 'Too many attempts. Try again in '.RateLimiter::availableIn($key).' seconds.']);
        }
        if (! Auth::attempt([$field => $validated['login'], 'password' => $validated['password']], $request->boolean('remember'))) {
            RateLimiter::hit($key, 60);
            throw ValidationException::withMessages(['login' => 'The supplied administrator credentials are invalid.']);
        }

        $request->session()->regenerate();
        $user = $request->user();
        if (! $user || ! $user->isStaff() || $user->isSuspended()) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            abort(404);
        }

        RateLimiter::clear($key);
        $user->forceFill(['last_login_at' => now(), 'last_active_at' => now()])->saveQuietly();
        $history = StaffLoginHistory::query()->create([
            'user_id' => $user->id,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'result' => 'success',
            'logged_in_at' => now(),
        ]);
        $request->session()->put('azari_staff_login_history_id', $history->id);
        return redirect()->intended(route('azari.admin.dashboard'))->with('success', 'Welcome back.');
    }

    public function destroy(Request $request): RedirectResponse
    {
        if ($historyId = $request->session()->pull('azari_staff_login_history_id')) {
            StaffLoginHistory::query()->whereKey($historyId)->where('user_id', $request->user()?->id)->update(['logged_out_at' => now()]);
        }
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('azari.admin.login')->with('success', 'You have been signed out.');
    }
}
