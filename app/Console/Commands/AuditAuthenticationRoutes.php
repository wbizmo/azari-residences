<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Route;

class AuditAuthenticationRoutes extends Command
{
    protected $signature = 'azari:auth-audit {--strict : Fail when production session settings are unsafe}';

    protected $description = 'Audit authentication routes, protected-area middleware and production session safety.';

    public function handle(): int
    {
        $issues = [];
        $warnings = [];
        $routes = collect(Route::getRoutes()->getRoutes());

        $required = [
            'login' => ['guest'],
            'register' => ['guest'],
            'password.email' => ['guest', 'throttle:5,1'],
            'password.store' => ['guest', 'throttle:5,1'],
            'logout' => ['auth', 'auth.session'],
            'azari.admin.login.store' => ['throttle:5,1'],
            'azari.admin.dashboard' => ['auth.session', 'azari.staff'],
            'user.dashboard' => ['auth', 'auth.session', 'verified', 'azari.customer'],
        ];

        foreach ($required as $name => $expectedMiddleware) {
            $route = $routes->first(fn ($candidate) => $candidate->getName() === $name);

            if (! $route) {
                $issues[] = "Required route [{$name}] is missing.";
                continue;
            }

            $actual = $route->gatherMiddleware();

            foreach ($expectedMiddleware as $middleware) {
                if (! in_array($middleware, $actual, true)) {
                    $issues[] = "Route [{$name}] is missing middleware [{$middleware}].";
                }
            }
        }

        $customerLoginPost = $routes->first(
            fn ($candidate) => in_array('POST', $candidate->methods(), true)
                && $candidate->uri() === 'login'
        );

        if (! $customerLoginPost) {
            $issues[] = 'Customer POST /login route is missing.';
        } elseif (! in_array('throttle:10,1', $customerLoginPost->gatherMiddleware(), true)) {
            $issues[] = 'Customer POST /login route is missing middleware [throttle:10,1].';
        }

        foreach ($routes as $route) {
            $name = (string) $route->getName();
            $uri = $route->uri();
            $middleware = $route->gatherMiddleware();

            $customerArea = str_starts_with($name, 'user.') || str_starts_with($uri, 'account');
            $staffArea = str_starts_with($name, 'azari.admin.') && ! str_contains($name, '.login');

            if ($customerArea && ! in_array('azari.customer', $middleware, true)) {
                $issues[] = "Customer route [{$name}] lacks azari.customer.";
            }

            if ($customerArea && ! in_array('auth.session', $middleware, true)) {
                $issues[] = "Customer route [{$name}] lacks auth.session.";
            }

            if ($staffArea && ! in_array('azari.staff', $middleware, true) && ! in_array('azari.admin', $middleware, true)) {
                $issues[] = "Staff route [{$name}] lacks staff/admin protection.";
            }

        }

        if (config('session.serialization') !== 'json') {
            $issues[] = 'Session serialization must remain json.';
        }

        if (! config('session.http_only')) {
            $issues[] = 'Session cookies must be HTTP-only.';
        }

        if (app()->environment('production')) {
            if (! config('session.secure')) {
                $issues[] = 'SESSION_SECURE_COOKIE must be true in production.';
            }

            if (! config('session.encrypt')) {
                $issues[] = 'SESSION_ENCRYPT must be true in production.';
            }

            if (config('app.debug')) {
                $issues[] = 'APP_DEBUG must be false in production.';
            }

            if ((string) config('session.driver') === 'array') {
                $issues[] = 'The array session driver cannot be used in production.';
            }
        } else {
            $warnings[] = 'APP_ENV is not production; production-only checks are advisory.';
        }

        foreach ($warnings as $warning) {
            $this->warn($warning);
        }

        foreach (array_values(array_unique($issues)) as $issue) {
            $this->error($issue);
        }

        if ($issues === []) {
            $this->info('Authentication and route protection audit passed.');
            return self::SUCCESS;
        }

        if (! $this->option('strict')) {
            $this->warn('Audit found issues, but --strict was not supplied.');
            return self::SUCCESS;
        }

        return self::FAILURE;
    }
}
