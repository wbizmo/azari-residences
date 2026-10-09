<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * High-risk workflows require a recent password re-entry, not merely an
 * hours-old login cookie. In particular, never auto-replay a financial POST.
 */
class EnsureRecentPasswordConfirmation
{
    private const MAX_AGE_SECONDS = 15 * 60;

    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()) {
            abort(401);
        }

        $confirmed = (int) $request->session()->get('auth.password_confirmed_at', 0);
        if ($confirmed < time() - self::MAX_AGE_SECONDS || $confirmed > time() + 60) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Confirm your password again before this sensitive action.',
                    'password_confirmation_url' => route('password.confirm'),
                ], 428);
            }

            // Do not use "intended" on non-GET requests: redirecting back
            // to a POST-only action after confirmation would lose the form
            // payload and can produce an unexpected unsafe retry.
            return redirect()->route('password.confirm')
                ->with('warning', 'Confirm your password, return to the page, and resubmit this sensitive action.');
        }

        return $next($request);
    }
}
