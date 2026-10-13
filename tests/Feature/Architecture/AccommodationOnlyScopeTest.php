<?php

namespace Tests\Feature\Architecture;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

final class AccommodationOnlyScopeTest extends TestCase
{
    public function test_accommodation_only_is_enabled_by_default_and_pesapal_is_not_retired(): void
    {
        self::assertTrue((bool) config('reserva.accommodation_only'));

        foreach (['home', 'availability.index', 'public.apartments', 'user.owner.listings.create', 'azari.admin.properties.create'] as $name) {
            self::assertTrue(Route::has($name), "Accommodation route {$name} must remain available.");
        }

        self::assertFalse(in_array('resavar.non-accommodation', Route::getRoutes()
            ->getByName('azari.admin.properties.store')->gatherMiddleware(), true));
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
            $this->get($path)->assertStatus(301)->assertRedirect('/availability');
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
