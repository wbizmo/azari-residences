<?php

namespace App\Support;

use Illuminate\Http\Request;

final class StaffPermissionResolver
{
    private const MODULE_MAP = [
        'dashboard' => 'dashboard',
        'bookings' => 'bookings',
        's56' => 'availability',
        'payments' => 'payments',
        'properties' => 'properties',
        'inventory' => 'properties',
        'locations' => 'properties',
        'room-types' => 'properties',
        'users' => 'guests',
        'identities' => 'guest-identities',
        'staff' => 'staff',
        'service-requests' => 'service-requests',
        'support' => 'support-tickets',
        'documents' => 'documents',
        'communications' => 'communications',
        'cms' => 'cms',
        'content' => 'cms',
        'settings' => 'settings',
        'reports' => 'reports',
        'audit-logs' => 'audit-logs',

        'system' => 'system-health',
        'owner-listings' => 'property-owners',
        'owner-withdrawals' => 'owner-withdrawals',
        'owner-payout-profiles' => 'owner-withdrawals',
        'owner-settings' => 'owner-settings',

    ];

    public static function permissionFor(Request $request): ?string
    {
        $routeName = (string) optional($request->route())->getName();
        if (! str_starts_with($routeName, 'azari.admin.')) return null;

        $parts = explode('.', substr($routeName, strlen('azari.admin.')));
        $segment = $parts[0] ?? '';
        if ($segment === 'dashboard') return null;
        $module = self::MODULE_MAP[$segment] ?? null;
        if (! $module) return null;

        return $module.'.'.self::actionFor($request, $parts);
    }

    private static function actionFor(Request $request, array $parts): string
    {
        $name = implode('.', $parts);
        if (preg_match('/(?:export|download|document|proof)/', $name)) return 'export';
        if (preg_match('/(?:create|store|link)/', $name)) return 'create';
        if (preg_match('/(?:edit|update|toggle|suspend|reactivate|review|password|permissions|cancel|test|reconcile)/', $name)) return 'edit';
        if (preg_match('/(?:destroy|delete|archive)/', $name)) return 'delete';
        if (in_array($request->method(), ['POST'], true)) return 'create';
        if (in_array($request->method(), ['PUT', 'PATCH'], true)) return 'edit';
        if ($request->method() === 'DELETE') return 'delete';
        return 'view';
    }
}
