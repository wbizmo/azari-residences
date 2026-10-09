<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Route;

class AuditAuthorizationOwnership extends Command
{
    protected $signature = 'azari:authorization-audit {--strict : Return failure when any issue is found}';

    protected $description = 'Audit staff authorization boundaries and customer ownership/IDOR protection.';

    public function handle(): int
    {
        $issues = [];
        $routes = collect(Route::getRoutes()->getRoutes());

        $requiredMiddleware = [
            'user.bookings.show' => ['azari.customer', 'azari.owns-route'],
            'user.bookings.receipt' => ['azari.customer', 'azari.owns-route'],
            'user.payments.show' => ['azari.customer', 'azari.owns-route'],
            'user.payments.retry' => ['azari.customer', 'azari.owns-route'],
            'user.notifications.read' => ['azari.customer', 'azari.owns-route'],
            'user.security.sessions.destroy' => ['azari.customer', 'azari.owns-route'],
            'azari.admin.settings.integrations.update' => ['azari.staff', 'azari.admin'],
            'azari.admin.payments.providers.test' => ['azari.staff', 'azari.admin'],
            'azari.admin.payments.store' => ['azari.staff', 'azari.permission:payments.manage'],
            'azari.admin.payments.reconcile' => ['azari.staff', 'azari.permission:payments.manage'],
            'azari.admin.bookings.cancel' => ['azari.staff', 'azari.permission:bookings.edit', 'azari.step-up'],
            'azari.admin.payments.refunds.dispatch' => ['azari.staff', 'azari.permission:payments.manage', 'azari.step-up'],
            'azari.admin.payments.refunds.reconcile' => ['azari.staff', 'azari.permission:payments.manage', 'azari.step-up'],
            'azari.admin.owner-withdrawals.reconcile-paid' => ['azari.staff', 'azari.permission:owner-withdrawals.process', 'azari.step-up'],
            'azari.admin.owner-withdrawals.reconcile-not-paid' => ['azari.staff', 'azari.permission:owner-withdrawals.process', 'azari.step-up'],
            'azari.admin.system-health.failed-jobs.retry' => ['azari.staff', 'azari.permission:system-health.manage', 'throttle:5,1'],
        ];

        foreach ($requiredMiddleware as $name => $expected) {
            $route = $routes->first(fn ($candidate) => $candidate->getName() === $name);

            if (! $route) {
                $issues[] = "Required route [{$name}] is missing.";
                continue;
            }

            $actual = $route->gatherMiddleware();

            foreach ($expected as $middleware) {
                if (! in_array($middleware, $actual, true)) {
                    $issues[] = "Route [{$name}] is missing middleware [{$middleware}].";
                }
            }
        }

        foreach ($routes as $route) {
            $name = (string) $route->getName();
            $methods = $route->methods();
            $middleware = $route->gatherMiddleware();

            if (str_starts_with($name, 'user.')
                && array_intersect($methods, ['POST', 'PUT', 'PATCH', 'DELETE'])
                && ! in_array('azari.customer', $middleware, true)) {
                $issues[] = "Mutating user route [{$name}] lacks azari.customer.";
            }

            $authExceptions = [
                'azari.admin.login.store',
                'azari.admin.login',
                'azari.admin.logout',
            ];

            if (
                str_starts_with($name, 'azari.admin.')
                && ! in_array($name, $authExceptions, true)
                && array_intersect($methods, ['POST', 'PUT', 'PATCH', 'DELETE'])
                && ! in_array('azari.staff', $middleware, true)
                && ! in_array('azari.admin', $middleware, true)
            ) {
                $issues[] = "Mutating admin route [{$name}] lacks a staff/admin boundary.";
            }
        }

        if ($issues === []) {
            $this->info('Authorization and ownership audit passed.');

            return self::SUCCESS;
        }

        foreach ($issues as $issue) {
            $this->warn($issue);
        }

        if ($this->option('strict')) {
            return self::FAILURE;
        }

        $this->warn('Audit found issues, but --strict was not supplied.');

        return self::SUCCESS;
    }
}
