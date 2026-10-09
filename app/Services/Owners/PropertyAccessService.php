<?php
namespace App\Services\Owners;

use App\Models\Property;
use App\Models\PropertyStaffMembership;
use App\Models\User;

class PropertyAccessService
{
    public function can(User $user, Property $property, string $capability): bool
    {
        if ((int) $property->owner_id === (int) $user->getKey()) {
            return true;
        }

        $membership = PropertyStaffMembership::query()
            ->where('property_id', $property->getKey())
            ->where('user_id', $user->getKey())
            ->whereNull('revoked_at')
            ->whereNotNull('accepted_at')
            ->first();

        if (! $membership) {
            return false;
        }

        $roleDefaults = [
            'manager' => ['inventory.manage', 'operations.manage', 'messages.manage', 'bookings.view', 'support.manage'],
            'front_desk' => ['operations.manage', 'messages.manage', 'bookings.view', 'support.manage'],
            'inventory_editor' => ['inventory.manage', 'bookings.view'],
            'finance_viewer' => ['finance.view', 'bookings.view'],
            'support_agent' => ['messages.manage', 'support.manage', 'bookings.view'],
        ];

        $grants = array_values(array_unique(array_merge(
            $roleDefaults[$membership->role] ?? [],
            $membership->capabilities ?? []
        )));

        return in_array($capability, $grants, true);
    }

    public function assert(User $user, Property $property, string $capability): void
    {
        abort_unless($this->can($user, $property, $capability), 404);
    }
}
