<?php

namespace App\Http\Middleware;

use App\Support\StaffPermissionResolver;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAzariStaff
{
    public function handle(Request $request, Closure $next, ...$roles): Response
    {
        $user = $request->user();
        if (! $user) {
            return $request->expectsJson()
                ? response()->json(['message' => 'Unauthenticated.'], 401)
                : redirect()->guest(route('azari.admin.login'));
        }
        if (! $user->isStaff() || $user->isSuspended()) abort(404);

        if ($roles !== []) {
            $effective = $user->isAdministrator() ? 'administrator' : (string) $user->staff_role;
            abort_unless(in_array($effective, $roles, true), 403);
        }

        if (! $user->isAdministrator()) {
            $permission = StaffPermissionResolver::permissionFor($request);
            if ($permission && ! $user->hasPermission($permission)) abort(403);
        }

        $user->forceFill(['last_active_at' => now()])->saveQuietly();
        return $next($request);
    }
}
