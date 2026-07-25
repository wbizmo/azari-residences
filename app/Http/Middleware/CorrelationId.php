<?php
namespace App\Http\Middleware;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;
class CorrelationId {
    public function handle(Request $request, Closure $next): Response {
        $id = $request->headers->get('X-Request-ID');
        if (!is_string($id) || !preg_match('/^[A-Za-z0-9._-]{8,100}$/', $id)) $id=(string) Str::uuid();
        $request->attributes->set('request_id',$id);
        $response=$next($request); $response->headers->set('X-Request-ID',$id); return $response;
    }
}
