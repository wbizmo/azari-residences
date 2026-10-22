<?php

namespace Tests\Feature\Architecture;

use App\Console\Commands\ResarvaReleaseGate;
use Illuminate\Support\Facades\Route;
use ReflectionMethod;
use Tests\TestCase;

final class AccommodationOnlyScopeTest extends TestCase
{
    public function test_accommodation_only_is_enabled_by_default_and_pesapal_is_not_retired(): void
    {
        self::assertTrue((bool) config('reserva.accommodation_only'));

        foreach (['home', 'availability.index', 'public.apartments', 'user.owner.listings.create', 'azari.admin.properties.create'] as $name) {
            self::assertTrue(Route::has($name), "Accommodation route {$name} must remain available.");
        }

        foreach (['azari.admin.properties.store', 'public.payment.initialise', 'payments.webhook', 'payments.callback'] as $name) {
            $route = Route::getRoutes()->getByName($name);
            self::assertNotNull($route, "Preserve booking and payment route {$name}.");
            self::assertNotContains('resavar.non-accommodation', $route->gatherMiddleware(), $name);
        }
    }

    public function test_production_scope_cannot_be_reenabled_by_a_legacy_environment_flag(): void
    {
        $original = [];
        foreach (['APP_ENV', 'RESAVAR_ACCOMMODATION_ONLY'] as $name) {
            $original[$name] = [
                'environment' => array_key_exists($name, $_ENV) ? $_ENV[$name] : null,
                'server' => array_key_exists($name, $_SERVER) ? $_SERVER[$name] : null,
                'process' => getenv($name),
            ];
        }

        try {
            $_ENV['APP_ENV'] = $_SERVER['APP_ENV'] = 'production';
            $_ENV['RESAVAR_ACCOMMODATION_ONLY'] = $_SERVER['RESAVAR_ACCOMMODATION_ONLY'] = 'false';
            putenv('APP_ENV=production');
            putenv('RESAVAR_ACCOMMODATION_ONLY=false');

            $productionConfiguration = require config_path('reserva.php');
            self::assertTrue($productionConfiguration['accommodation_only']);
        } finally {
            foreach ($original as $name => $sources) {
                if ($sources['environment'] === null) {
                    unset($_ENV[$name]);
                } else {
                    $_ENV[$name] = $sources['environment'];
                }
                if ($sources['server'] === null) {
                    unset($_SERVER[$name]);
                } else {
                    $_SERVER[$name] = $sources['server'];
                }
                $sources['process'] === false
                    ? putenv($name)
                    : putenv($name.'='.$sources['process']);
            }
        }
    }

    public function test_release_gate_rejects_legacy_activation_and_preserves_read_only_archives(): void
    {
        $method = new ReflectionMethod(ResarvaReleaseGate::class, 'legacyScopeSafe');
        $command = new ResarvaReleaseGate;

        self::assertTrue($method->invoke($command), 'Retired routes and background jobs must remain inaccessible.');

        config()->set('reserva.accommodation_only', false);
        self::assertFalse($method->invoke($command), 'A legacy product opt-in must fail the current release gate.');

        config()->set('reserva.accommodation_only', true);
        Route::get('/__scope-regression-test', static fn () => 'not allowed')
            ->name('user.travel.unprotected-new-endpoint');
        self::assertFalse($method->invoke($command), 'A newly introduced travel route must not bypass retirement.');
    }

    public function test_travel_supplier_webhooks_and_partner_api_are_gone_without_touching_booking_callbacks(): void
    {
        $this->postJson('/webhooks/travel/unconfigured', [])->assertStatus(410);
        $this->postJson('/webhooks/channels/unconfigured', [])->assertStatus(410);
        $this->getJson('/api/partners/v1/stays')->assertStatus(410);
        $this->postJson('/api/partners/v1/intents', [])->assertStatus(410);
    }

    public function test_legacy_marketing_urls_redirect_to_stay_search(): void
    {
        foreach (['/restaurant', '/airport-transfers', '/services', '/concierge', '/housekeeping', '/local-guide'] as $path) {
            $this->get($path)->assertStatus(301)->assertRedirect(route('availability.index'));
        }
    }

    public function test_non_accommodation_route_mutations_have_the_server_side_guard(): void
    {
        foreach ([
            'user.travel.store',
            'user.travel.cancel',
            'user.dining.store',
            'user.itineraries.review-assembly',
            'azari.admin.travel.suppliers.store',
            'azari.admin.dining.store',
            'azari.admin.channels.sync',
            'azari.admin.channels.events',
            'user.owner.channels.sync',
            'travel.supplier.webhook',
            'channels.webhook',
            'partner.v1.intents',
        ] as $name) {
            $route = Route::getRoutes()->getByName($name);
            self::assertNotNull($route, "Preserve old route name {$name} during retirement.");
            self::assertContains('resavar.non-accommodation', $route->gatherMiddleware(), $name);
        }
    }

    public function test_travel_background_tasks_are_not_scheduled(): void
    {
        $schedules = app(\Illuminate\Console\Scheduling\Schedule::class);
        $commands = collect($schedules->events())->map(fn ($event) => (string) $event->command)->all();
        $joined = implode("\n", $commands);
        self::assertStringNotContainsString('azari:sync-channels', $joined);
        self::assertStringNotContainsString('resavar:process-travel-webhooks', $joined);
        self::assertStringNotContainsString('resavar:dining-arrival-reminders', $joined);
        self::assertStringContainsString('azari:reconcile-payments', $joined);
    }
}
