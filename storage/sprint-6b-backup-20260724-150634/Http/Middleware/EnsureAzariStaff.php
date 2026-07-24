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
            && (bool) $user->is_active
            && (
                (bool) $user->is_admin
                || in_array($user->staff_role, ['administrator', 'support'], true)
            ), 403);

        if ($roles !== []) {
            $effectiveRole = $user->staff_role ?: ((bool) $user->is_admin ? 'administrator' : null);
            abort_unless(in_array($effectiveRole, $roles, true), 403);
        }

        return $next($request);
    }
}
