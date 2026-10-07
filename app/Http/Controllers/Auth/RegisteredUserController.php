<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\AuthAbuseGuard;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class RegisteredUserController extends Controller
{
    /**
     * Display the registration view.
     */
    public function create(AuthAbuseGuard $abuse): View
    {
        return view('auth.register', [
            'authFormToken' => $abuse->formToken('register'),
        ]);
    }

    /**
     * Handle an incoming registration request.
     *
     * @throws ValidationException
     */
    public function store(Request $request, AuthAbuseGuard $abuse): RedirectResponse
    {
        $abuse->assertHumanForm(
            $request,
            'register',
            (string) $request->input('email'),
            5,
            600,
            2
        );

        $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'email' => ['required', 'string', 'lowercase', 'email:rfc', 'max:190', 'unique:'.User::class],
            'password' => ['required', 'confirmed', Password::min(12)->letters()->mixedCase()->numbers()],
        ]);

        $user = new User;
        $user->fill([
            'name' => trim((string) $request->name),
            'email' => mb_strtolower(trim((string) $request->email)),
            'password' => Hash::make($request->password),
            'timezone' => config('localization.platform_timezone', 'UTC'),
        ]);
        $user->forceFill([
            'account_type' => 'customer',
            'status' => 'pending_verification',
            'is_active' => true,
            'email_verified_at' => null,
        ])->save();

        event(new Registered($user));

        Auth::login($user);

        return redirect()->route('verification.notice')->with('success', 'Account created. Verify your email within 7 days to keep the account open.');
    }
}
