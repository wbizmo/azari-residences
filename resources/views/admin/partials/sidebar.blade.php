@php
    $siteName = class_exists(\App\Models\SiteSetting::class)
        ? \App\Models\SiteSetting::valueFor('site_name', 'Reserva')
        : 'Reserva';
    $adminActor = auth()->user();
    $can = fn (string $permission): bool => (bool) ($adminActor?->isAdministrator() || $adminActor?->hasPermission($permission));

    $items = [
        ['azari.admin.bookings.index','calendar_month','Bookings','bookings.view'],
        ['azari.admin.s56.calendar','event_available','Availability','availability.view'],

        ['azari.admin.properties.index','apartment','Properties','properties.view'],
        ['azari.admin.inventory.index','inventory_2','Inventory & categories','properties.view'],
        ['azari.admin.locations.index','location_on','Locations','properties.view'],
        ['azari.admin.room-types.index','category','Categories','properties.view'],
        ['azari.admin.owner-listings.index','real_estate_agent','Owner listings','property-owners.view'],
        ['azari.admin.owner-withdrawals.index','payments','Owner withdrawals','owner-withdrawals.view'],
        ['azari.admin.owner-settings.edit','tune','Owner marketplace','owner-settings.manage'],

        ['azari.admin.users.index','group','Users','guests.view'],
        ['azari.admin.payments.index','account_balance_wallet','Payments','payments.view'],
        ['azari.admin.documents.index','description','Documents','documents.view'],
        ['azari.admin.service-requests.index','room_service','Service requests','service-requests.view'],
        ['azari.admin.support.index','support_agent','Support tickets','support-tickets.view'],
        ['azari.admin.cms.index','edit_note','CMS','cms.view'],
        ['azari.admin.reports.index','monitoring','Reports','reports.view'],
        ['azari.admin.audit-logs.index','history','Audit logs','audit-logs.view'],
        ['azari.admin.promotions.index','campaign','Promotions','promotions.view'],
        ['azari.admin.vouchers.index','sell','Vouchers','vouchers.view'],
        ['azari.admin.communications.index','outgoing_mail','Communications','system-health.view'],
        ['azari.admin.system-health.index','health_metrics','System health','system-health.view'],
    ];

@endphp
<aside class="az-admin-sidebar" id="az-admin-sidebar" data-admin-sidebar aria-label="Administration navigation">
    <div class="az-sidebar-brand"><a href="{{ route('azari.admin.dashboard') }}" class="az-sidebar-brand__link" aria-label="{{ $siteName }} dashboard"><img class="az-sidebar-brand__logo" src="{{ asset('images/resavar-logo-light.png') }}?v=20261004-4" alt="{{ $siteName }}"></a><button type="button" class="az-icon-button az-sidebar-close" data-sidebar-close aria-label="Close navigation"><span class="material-symbols-outlined" aria-hidden="true">close</span></button></div>
    <div class="az-sidebar-context"><span class="az-sidebar-context__eyebrow">Administration</span><strong>Residence operations</strong></div>
    <nav class="az-sidebar-nav">
        <section class="az-nav-section"><h2>Overview</h2><a href="{{ route('azari.admin.dashboard') }}" class="az-nav-link {{ request()->routeIs('azari.admin.dashboard') ? 'is-active' : '' }}"><span class="material-symbols-outlined">space_dashboard</span><span>Dashboard</span></a></section>
        <section class="az-nav-section"><h2>Modules</h2>
            @foreach($items as [$route,$icon,$label,$permission])
                @if(Route::has($route) && $can($permission))
                    <a href="{{ route($route) }}" class="az-nav-link {{ request()->routeIs(str_replace('.index','.*',$route)) || request()->routeIs($route) ? 'is-active' : '' }}"><span class="material-symbols-outlined" aria-hidden="true">{{ $icon }}</span><span>{{ $label }}</span></a>
                @endif
            @endforeach
            @if(Route::has('azari.admin.staff.index') && $adminActor?->isAdministrator())
                <a href="{{ route('azari.admin.staff.index') }}" class="az-nav-link {{ request()->routeIs('azari.admin.staff.*') ? 'is-active' : '' }}"><span class="material-symbols-outlined">badge</span><span>Staff</span></a>
            @endif
        </section>
    </nav>
    <div class="az-sidebar-footer"><span class="material-symbols-outlined">verified_user</span><div><strong>Secure administration</strong><span>Authorised staff only</span></div></div>
</aside>
