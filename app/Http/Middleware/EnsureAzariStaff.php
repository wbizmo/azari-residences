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

        abort_unless($user->isStaff() && ! $user->isSuspended(), 404);

        if ($roles !== []) {
            $effective = $user->isAdministrator() ? 'administrator' : (string) $user->staff_role;
            abort_unless(in_array($effective, $roles, true), 404);
        }

        if (! $user->isAdministrator()) {
            $permission = StaffPermissionResolver::permissionFor($request);
            abort_unless(! $permission || $user->hasPermission($permission), 404);
        }

        $user->forceFill(['last_active_at' => now()])->saveQuietly();

        return $next($request);
    }
}
