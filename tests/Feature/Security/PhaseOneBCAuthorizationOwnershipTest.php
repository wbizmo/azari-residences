<?php

namespace Tests\Feature\Security;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class PhaseOneBCAuthorizationOwnershipTest extends TestCase
{
    public function test_sensitive_customer_routes_have_ownership_protection(): void
    {
        foreach ([
            'user.bookings.show',
            'user.bookings.receipt',
            'user.payments.show',
            'user.payments.retry',
            'user.identity.download',
            'user.guests.identity.store',
            'user.guests.identity.download',
            'user.notifications.read',
            'user.security.sessions.destroy',
        ] as $name) {
            $this->assertRouteMiddleware($name, ['azari.customer', 'azari.owns-route']);
        }
    }

    public function test_sensitive_staff_mutations_have_explicit_authorization(): void
    {
        $expectations = [
            'azari.admin.settings.integrations.update' => ['azari.staff', 'azari.admin'],
            'azari.admin.payments.providers.test' => ['azari.staff', 'azari.admin'],
            'azari.admin.payments.store' => ['azari.staff', 'azari.permission:payments.manage'],
            'azari.admin.payments.reconcile' => ['azari.staff', 'azari.permission:payments.manage'],
            'azari.admin.identities.types.store' => ['azari.staff', 'azari.admin'],
            'azari.admin.identities.types.update' => ['azari.staff', 'azari.admin'],
            'azari.admin.identities.users.review' => ['azari.staff', 'azari.permission:identities.manage'],
            'azari.admin.identities.guests.review' => ['azari.staff', 'azari.permission:identities.manage'],
        ];

        foreach ($expectations as $name => $middleware) {
            $this->assertRouteMiddleware($name, $middleware);
        }
    }

    public function test_authorization_audit_passes_strictly(): void
    {
        $this->artisan('azari:authorization-audit --strict')->assertExitCode(0);
    }

    private function assertRouteMiddleware(string $name, array $expected): void
    {
        $route = collect(Route::getRoutes()->getRoutes())
            ->first(fn ($candidate) => $candidate->getName() === $name);

        $this->assertNotNull($route, "Route [{$name}] was not found.");

        $actual = $route->gatherMiddleware();

        foreach ($expected as $middleware) {
            $this->assertContains(
                $middleware,
                $actual,
                "Route [{$name}] is missing middleware [{$middleware}]."
            );
        }
    }
}
