<?php

use App\Http\Middleware\EnsureAdmin;
use App\Http\Middleware\EnsureAzariCustomer;
use App\Http\Middleware\EnsureDojahVerified;
use App\Http\Middleware\EnsureAzariStaff;
use App\Http\Middleware\EnsureStaffPermission;
use App\Http\Middleware\EnsureRouteModelOwnership;
use App\Http\Middleware\EnsureRecentPasswordConfirmation;
use App\Http\Middleware\CorrelationId;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\SetLocale;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->append([CorrelationId::class, SecurityHeaders::class]);
        $middleware->web(append: [SetLocale::class]);
        $middleware->alias([
            'azari.staff' => EnsureAzariStaff::class,
            'azari.customer' => EnsureAzariCustomer::class,
            'azari.identity.verified' => EnsureDojahVerified::class,
            'azari.admin' => EnsureAdmin::class,
            'azari.permission' => EnsureStaffPermission::class,
            'azari.owns-route' => EnsureRouteModelOwnership::class,
            'azari.step-up' => EnsureRecentPasswordConfirmation::class,
        ]);
        $middleware->validateCsrfTokens(except: [
            'payments/*/webhook',
            'payments/*/callback/*',
            'webhooks/twilio/message-status',
            'webhooks/dojah',
        ]);
        $middleware->trustProxies(at: '*');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
