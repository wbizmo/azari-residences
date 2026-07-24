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
        abort_unless($user && ! $user->isStaff() && ! $user->isSuspended(), 404);
        $user->forceFill(['last_active_at' => now()])->saveQuietly();
        return $next($request);
    }
}
