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
        abort_unless($user && (bool) $user->is_active, 404);

        $effectiveRole = $user->staff_role ?: ((bool) $user->is_admin ? 'administrator' : null);
        abort_unless((bool) $user->is_admin || in_array($effectiveRole, ['administrator', 'support'], true), 404);

        if ($roles !== []) {
            abort_unless(in_array($effectiveRole, $roles, true), 404);
        }

        return $next($request);
    }
}
