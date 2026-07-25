<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user) {
            return $request->expectsJson()
                ? response()->json(['message' => 'Unauthenticated.'], 401)
                : redirect()->guest(route('azari.admin.login'));
        }
        if (! $user->isStaff()) abort(404);
        if (! $user->isAdministrator()) abort(403);
        if ($user->isSuspended()) abort(404);
        return $next($request);
    }
}
