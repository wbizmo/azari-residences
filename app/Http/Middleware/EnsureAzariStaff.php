<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAzariStaff
{
    public function handle(Request $request, Closure $next, ...$roles): Response
    {
        $user = $request->user();

        abort_unless(
            $user
            && $user->is_active
            && in_array($user->staff_role, ['administrator', 'support'], true),
            403
        );

        if ($roles !== [] && ! in_array($user->staff_role, $roles, true)) {
            abort(403);
        }

        return $next($request);
    }
}
