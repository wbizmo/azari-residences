<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\AuthAbuseGuard;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PasswordResetLinkController extends Controller
{
    /**
     * Display the password reset link request view.
     */
    public function create(AuthAbuseGuard $abuse): View
    {
        return view('auth.forgot-password', [
            'authFormToken' => $abuse->formToken('password-reset-request'),
        ]);
    }

    /**
     * Handle an incoming password reset link request.
     *
     * @throws ValidationException
     */
    public function store(Request $request, AuthAbuseGuard $abuse): RedirectResponse
    {
        $abuse->assertHumanForm(
            $request,
            'password-reset-request',
            (string) $request->input('email'),
            4,
            900,
            2
        );

        $data = $request->validate([
            'email' => ['required', 'email:rfc', 'max:190'],
        ]);

        $user = User::query()
            ->whereRaw('LOWER(email) = ?', [mb_strtolower((string) $data['email'])])
            ->first();

        if ($user && ! $user->isSuspended()) {
            Password::sendResetLink(['email' => $user->email]);
        }

        // Do not reveal whether an email address exists in the database.
        return back()->with('status', 'If that address belongs to an eligible Resavar account, a password reset link has been sent.');
    }
}
