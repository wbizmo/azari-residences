<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(auth()->check() && (bool) auth()->user()->is_admin, 404);
        return $next($request);
    }
}
