<?php

namespace Tests\Feature\Security;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class PhaseOnePrivilegedStepUpTest extends TestCase
{
    use RefreshDatabase;

    public function test_expired_password_confirmation_blocks_financial_action_without_replaying_post(): void
    {
        Route::middleware(['web', 'auth', 'azari.step-up'])
            ->post('/_test/resavar-privileged-action', fn () => response('allowed', 200))
            ->name('resavar.test.privileged');

        $actor = User::factory()->create();
        $this->actingAs($actor)->withSession(['auth.password_confirmed_at' => time() - 3600])
            ->post('/_test/resavar-privileged-action')
            ->assertRedirect(route('password.confirm'));

        $this->actingAs($actor)->withSession(['auth.password_confirmed_at' => time()])
            ->post('/_test/resavar-privileged-action')
            ->assertOk()
            ->assertSee('allowed');
    }

    public function test_sensitive_owner_and_staff_routes_require_step_up(): void
    {
        foreach ([
            'user.owner.payout-profile.update',
            'user.owner.withdrawals.store',
            'azari.admin.owner-withdrawals.process',
            'azari.admin.owner-withdrawals.reconcile-paid',
            'azari.admin.owner-withdrawals.reconcile-not-paid',
            'azari.admin.owner-payout-profiles.verify',
            'azari.admin.payments.refunds.dispatch',
            'azari.admin.payments.refunds.reconcile',
            'azari.admin.payments.disputes.resolve',
            'azari.admin.bookings.cancel',
        ] as $name) {
            $route = collect(Route::getRoutes()->getRoutes())
                ->first(fn ($item) => $item->getName() === $name);
            $this->assertNotNull($route, "Expected protected route {$name}.");
            $this->assertContains('azari.step-up', $route->gatherMiddleware(), "{$name} lacks password step-up.");
        }
    }
}
