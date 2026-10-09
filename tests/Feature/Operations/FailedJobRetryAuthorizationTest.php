<?php

namespace Tests\Feature\Operations;

use App\Http\Controllers\Admin\OperationsController;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Tests\TestCase;

class FailedJobRetryAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_retry_route_requires_staff_permission_and_rate_limiting(): void
    {
        $route = collect(Route::getRoutes()->getRoutes())
            ->first(fn ($candidate) => $candidate->getName() === 'azari.admin.system-health.failed-jobs.retry');
        $this->assertNotNull($route);
        $middleware = $route->gatherMiddleware();
        $this->assertContains('azari.staff', $middleware);
        $this->assertContains('azari.permission:system-health.manage', $middleware);
        $this->assertContains('throttle:5,1', $middleware);
    }

    public function test_operator_cannot_requeue_unallowlisted_financial_job(): void
    {
        $admin = User::factory()->create([
            'is_admin' => true, 'staff_role' => 'administrator', 'account_type' => 'staff',
        ]);
        $id = DB::table('failed_jobs')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'connection' => 'database',
            'queue' => 'default',
            'payload' => json_encode([
                'displayName' => 'App\\Jobs\\HypotheticalPayoutTransfer',
                'data' => ['commandName' => 'App\\Jobs\\HypotheticalPayoutTransfer'],
            ], JSON_THROW_ON_ERROR),
            'exception' => 'Safe synthetic test failure; no real secret',
            'failed_at' => now(),
        ]);

        $this->actingAs($admin)
            ->post(route('azari.admin.system-health.failed-jobs.retry', $id))
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertDatabaseHas('failed_jobs', ['id' => $id]);
        $this->assertNotContains(
            'App\\Jobs\\HypotheticalPayoutTransfer',
            OperationsController::SAFE_RETRY_JOB_CLASSES
        );
    }
}
