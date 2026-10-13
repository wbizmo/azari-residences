<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Preserve legacy named routes and historical database records while refusing
 * all retired supplier, travel, channel and dining operations.
 *
 * This runs inside the route middleware stack, after authentication where
 * applicable, so unauthorized clients cannot inspect historical endpoints.
 * Reverting the scope is an explicit operator action, not an implicit fallback.
 */
final class RetireNonAccommodationRoutes
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('reserva.accommodation_only', true)) {
            return $next($request);
        }

        $message = 'Resavar now offers accommodation bookings only. This service is no longer available.';

        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json(['message' => $message], 410, [
                'Cache-Control' => 'no-store',
            ]);
        }

        return response($message, 410)->header('Cache-Control', 'no-store');
    }
}
