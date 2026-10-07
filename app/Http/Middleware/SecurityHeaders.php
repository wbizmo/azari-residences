<?php
namespace App\Http\Middleware;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
class SecurityHeaders {
    public function handle(Request $request, Closure $next): Response {
        $response=$next($request);
        foreach([
            'X-Content-Type-Options'=>'nosniff','X-Frame-Options'=>'SAMEORIGIN',
            'Referrer-Policy'=>'strict-origin-when-cross-origin',
            'Permissions-Policy'=>'camera=(), microphone=(), geolocation=()',
            'Cross-Origin-Opener-Policy'=>'same-origin',
            'Content-Security-Policy'=>"default-src 'self'; img-src 'self' data: https:; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src 'self' data: https://fonts.gstatic.com; script-src 'self' 'unsafe-inline'; connect-src 'self'; frame-ancestors 'self'; base-uri 'self'; form-action 'self'",
        ] as $k=>$v) if(!$response->headers->has($k)) $response->headers->set($k,$v);
        if($request->isSecure()) $response->headers->set('Strict-Transport-Security','max-age=31536000; includeSubDomains');

        if (
            $request->is('login')
            || $request->is('register')
            || $request->is('forgot-password')
            || $request->is('reset-password/*')
            || $request->is('verify-email*')
            || $request->is('azaridevadmin/login')
        ) {
            $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, private');
            $response->headers->set('Pragma', 'no-cache');
        }

        return $response;
    }
}
