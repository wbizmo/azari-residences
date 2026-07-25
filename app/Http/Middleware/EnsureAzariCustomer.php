<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAzariCustomer
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user) {
            return $request->expectsJson()
                ? response()->json(['message' => 'Unauthenticated.'], 401)
                : redirect()->guest(route('login'));
        }
        if ($user->isStaff()) abort(403);
        if ($user->isSuspended()) abort(404);

        $user->forceFill(['last_active_at' => now()])->saveQuietly();
        return $next($request);
    }
}
