<?php

use App\Http\Middleware\EnsureAdmin;
use App\Http\Middleware\EnsureAzariCustomer;
use App\Http\Middleware\EnsureAzariStaff;
use App\Http\Middleware\EnsureStaffPermission;
use App\Http\Middleware\CorrelationId;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->append([CorrelationId::class, SecurityHeaders::class]);
        $middleware->alias([
            'azari.staff' => EnsureAzariStaff::class,
            'azari.customer' => EnsureAzariCustomer::class,
            'azari.admin' => EnsureAdmin::class,
            'azari.permission' => EnsureStaffPermission::class,
        ]);
        $middleware->validateCsrfTokens(except: [
            'payments/*/webhook',
            'payments/*/callback/*',
        ]);
        $middleware->trustProxies(at: '*');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
        $exceptions->render(function (\Throwable $e, Request $request) {
            if (config('app.debug') || $e instanceof HttpExceptionInterface) {
                return null;
            }
            report($e);
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'The request could not be completed.',
                    'request_id' => $request->attributes->get('request_id'),
                ], 500);
            }
            return response()->view('errors.500', [
                'requestId' => $request->attributes->get('request_id'),
            ], 500);
        });
    })->create();
