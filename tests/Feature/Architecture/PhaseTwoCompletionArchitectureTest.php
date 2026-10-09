<?php

namespace Tests\Feature\Architecture;

use Tests\TestCase;

class PhaseTwoCompletionArchitectureTest extends TestCase
{
    public function test_phase_two_routes_and_safety_surfaces_are_present(): void
    {
        $routes = file_get_contents(base_path('routes/resavar-phase-two.php'));
        $serviceWorker = file_get_contents(public_path('sw.js'));
        $manifest = json_decode(file_get_contents(public_path('manifest.webmanifest')), true, 512, JSON_THROW_ON_ERROR);

        $this->assertStringContainsString('staff/invitations', $routes);
        $this->assertStringContainsString('/operations/tasks', $routes);
        $this->assertStringContainsString('/messages', $routes);
        $this->assertStringContainsString('/arrival/check-in', $routes);
        $this->assertStringContainsString('push-subscriptions', $routes);
        $this->assertStringContainsString('calendar/preview', $routes);
        $this->assertStringContainsString('calendar-changes/{log}/undo', $routes);

        $this->assertSame('standalone', $manifest['display']);
        $this->assertSame('#052058', $manifest['theme_color']);
        $this->assertStringContainsString('booking\\/.*payment', $serviceWorker);
        $this->assertStringContainsString('identity', $serviceWorker);
        $this->assertStringContainsString('documents?', $serviceWorker);
        $this->assertStringContainsString('cannot confirm live availability', $serviceWorker);
    }

    public function test_phase_two_migration_has_scoped_collaboration_and_auditable_workflows(): void
    {
        $migration = file_get_contents(database_path('migrations/2026_10_09_200000_complete_phase_two_platform.php'));

        foreach ([
            'property_staff_memberships',
            'property_staff_invitations',
            'property_operations_tasks',
            'booking_conversations',
            'booking_messages',
            'web_push_subscriptions',
            'before_snapshot',
            'sla_due_at',
            'edited_at',
            'arrival_time',
        ] as $expected) {
            $this->assertStringContainsString($expected, $migration);
        }
    }

    public function test_phase_two_keeps_canonical_inventory_and_search_services(): void
    {
        $inventory = file_get_contents(app_path('Services/Bookings/InventoryBulkUpdateService.php'));
        $search = file_get_contents(app_path('Services/Search/MarketplaceSearchService.php'));
        $owner = file_get_contents(app_path('Http/Controllers/User/OwnerCommercialInventoryController.php'));

        $this->assertStringContainsString('assertCommittedInventoryPreserved', $inventory);
        $this->assertStringContainsString('before_snapshot', $inventory);
        $this->assertStringContainsString('public function undo', $inventory);
        $this->assertStringContainsString("whereBetween('latitude'", $search);
        $this->assertStringContainsString('Antimeridian viewport', $search);
        $this->assertStringContainsString('PropertyAccessService', $owner);
    }
}
