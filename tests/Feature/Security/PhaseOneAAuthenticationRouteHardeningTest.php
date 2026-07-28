<?php

namespace Tests\Feature\Security;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class PhaseOneAAuthenticationRouteHardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_authentication_routes_have_layered_middleware(): void
    {
        $this->assertRouteMiddleware('login', ['guest']);
        $this->assertMethodUriMiddleware('POST', 'login', ['guest', 'throttle:10,1']);
        $this->assertRouteMiddleware('password.email', ['guest', 'throttle:5,1']);
        $this->assertRouteMiddleware('password.store', ['guest', 'throttle:5,1']);
        $this->assertRouteMiddleware('logout', ['auth', 'auth.session']);
    }

    public function test_customer_and_staff_areas_validate_the_authenticated_session(): void
    {
        $this->assertRouteMiddleware('user.dashboard', ['auth', 'auth.session', 'verified', 'azari.customer']);
        $this->assertRouteMiddleware('azari.admin.dashboard', ['auth.session', 'azari.staff']);
    }

    public function test_customer_login_regenerates_the_session_identifier(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('correct-password'),
            'email_verified_at' => now(),
        ]);

        $this->get('/login');
        $before = session()->getId();

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'correct-password',
        ]);

        $response->assertRedirect();
        $this->assertAuthenticatedAs($user);
        $this->assertNotSame($before, session()->getId());
    }

    public function test_customer_logout_invalidates_the_authenticated_session(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);

        $response = $this->actingAs($user)->post('/logout');

        $response->assertRedirect('/');
        $this->assertGuest();
    }

    public function test_suspended_customer_cannot_use_customer_area(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
            'suspended_at' => now(),
        ]);

        $this->actingAs($user)
            ->get(route('user.dashboard'))
            ->assertNotFound();
    }

    public function test_staff_cannot_cross_into_customer_area(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
            'staff_role' => 'administrator',
        ]);

        $this->actingAs($user)
            ->get(route('user.dashboard'))
            ->assertForbidden();
    }

    private function assertMethodUriMiddleware(string $method, string $uri, array $expected): void
    {
        $route = collect(Route::getRoutes()->getRoutes())
            ->first(fn ($candidate) => in_array($method, $candidate->methods(), true)
                && $candidate->uri() === $uri);

        $this->assertNotNull($route, "{$method} [{$uri}] route was not found.");

        $actual = $route->gatherMiddleware();

        foreach ($expected as $middleware) {
            $this->assertContains(
                $middleware,
                $actual,
                "{$method} [{$uri}] is missing middleware [{$middleware}]."
            );
        }
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
