<?php

namespace App\Http\Middleware;

use App\Models\IdentityVerification;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureDojahVerified
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || $user->isStaff()) {
            return $next($request);
        }

        if (IdentityVerification::userIsVerified((int) $user->id)) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Complete Dojah identity verification before continuing.',
                'code' => 'identity_verification_required',
            ], 403);
        }

        if ($request->isMethod('GET') || $request->isMethod('HEAD')) {
            $request->session()->put('url.intended', $request->fullUrl());
        }

        return redirect()
            ->route('user.identity.index')
            ->with('warning', 'Complete Dojah identity verification before continuing with bookings, payments, property listings or other protected Azari actions.');
    }
}
