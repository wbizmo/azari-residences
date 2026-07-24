@php
    $configuredLogo = null;
    $siteName = 'Azari Residences';

    if (class_exists(\App\Models\SiteSetting::class)) {
        $siteName = \App\Models\SiteSetting::valueFor('site_name', 'Azari Residences');
        $configuredLogo = \App\Models\SiteSetting::valueFor('admin_panel_logo')
            ?: \App\Models\SiteSetting::valueFor('site_logo');
    }

    $logoUrl = $configuredLogo
        ? \Illuminate\Support\Facades\Storage::url($configuredLogo)
        : null;
@endphp

<aside class="az-admin-sidebar" id="az-admin-sidebar" data-admin-sidebar aria-label="Administration navigation">
    <div class="az-sidebar-brand">
        <a href="{{ route('azari.admin.dashboard') }}" class="az-sidebar-brand__link" aria-label="{{ $siteName }} dashboard">
            @if ($logoUrl)
                <img src="{{ $logoUrl }}" alt="{{ $siteName }}" class="az-sidebar-brand__logo">
            @else
                <span class="az-sidebar-brand__text">{{ $siteName }}</span>
            @endif
        </a>

        <button
            type="button"
            class="az-icon-button az-sidebar-close"
            data-sidebar-close
            aria-label="Close navigation"
        >
            <span class="material-symbols-outlined" aria-hidden="true">close</span>
        </button>
    </div>

    <div class="az-sidebar-context">
        <span class="az-sidebar-context__eyebrow">Administration</span>
        <strong>Residence operations</strong>
    </div>

    <nav class="az-sidebar-nav">
        <section class="az-nav-section" aria-labelledby="az-nav-overview">
            <h2 id="az-nav-overview">Overview</h2>
            <a
                href="{{ route('azari.admin.dashboard') }}"
                class="az-nav-link {{ request()->routeIs('azari.admin.dashboard') ? 'is-active' : '' }}"
            >
                <span class="material-symbols-outlined" aria-hidden="true">space_dashboard</span>
                <span>Dashboard</span>
            </a>
        </section>

        <section class="az-nav-section" aria-labelledby="az-nav-operations">
            <h2 id="az-nav-operations">Operations</h2>

            @if (Route::has('azari.admin.bookings.index'))
                <a href="{{ route('azari.admin.bookings.index') }}" class="az-nav-link {{ request()->routeIs('azari.admin.bookings.*') ? 'is-active' : '' }}">
                    <span class="material-symbols-outlined" aria-hidden="true">calendar_month</span>
                    <span>Bookings</span>
                </a>
            @endif

            @if (Route::has('azari.admin.availability.index'))
                <a href="{{ route('azari.admin.availability.index') }}" class="az-nav-link {{ request()->routeIs('azari.admin.availability.*') ? 'is-active' : '' }}">
                    <span class="material-symbols-outlined" aria-hidden="true">event_available</span>
                    <span>Availability</span>
                </a>
            @endif

            @if (Route::has('azari.admin.properties.index'))
                <a href="{{ route('azari.admin.properties.index') }}" class="az-nav-link {{ request()->routeIs('azari.admin.properties.*') ? 'is-active' : '' }}">
                    <span class="material-symbols-outlined" aria-hidden="true">apartment</span>
                    <span>Properties</span>
                </a>
            @endif

            <a href="{{ url('/azari-admin/inventory') }}" class="az-nav-link {{ request()->is('azari-admin/inventory*') ? 'is-active' : '' }}">
                <span class="material-symbols-outlined" aria-hidden="true">inventory_2</span>
                <span>Inventory</span>
            </a>
        </section>

        <section class="az-nav-section" aria-labelledby="az-nav-people">
            <h2 id="az-nav-people">People</h2>

            @if (Route::has('azari.admin.users.index'))
                <a href="{{ route('azari.admin.users.index') }}" class="az-nav-link {{ request()->routeIs('azari.admin.users.*') ? 'is-active' : '' }}">
                    <span class="material-symbols-outlined" aria-hidden="true">group</span>
                    <span>Users</span>
                </a>
            @endif

            @if (Route::has('azari.admin.staff.index'))
                <a href="{{ route('azari.admin.staff.index') }}" class="az-nav-link {{ request()->routeIs('azari.admin.staff.*') ? 'is-active' : '' }}">
                    <span class="material-symbols-outlined" aria-hidden="true">badge</span>
                    <span>Staff</span>
                </a>
            @endif

            @if (Route::has('azari.admin.reviews.index'))
                <a href="{{ route('azari.admin.reviews.index') }}" class="az-nav-link {{ request()->routeIs('azari.admin.reviews.*') ? 'is-active' : '' }}">
                    <span class="material-symbols-outlined" aria-hidden="true">reviews</span>
                    <span>Reviews</span>
                </a>
            @endif
        </section>

        <section class="az-nav-section" aria-labelledby="az-nav-experience">
            <h2 id="az-nav-experience">Experience</h2>

            <a href="{{ url('/azari-admin/cms') }}" class="az-nav-link {{ request()->is('azari-admin/cms*') ? 'is-active' : '' }}">
                <span class="material-symbols-outlined" aria-hidden="true">edit_note</span>
                <span>CMS</span>
            </a>

            @if (Route::has('azari.admin.media.index'))
                <a href="{{ route('azari.admin.media.index') }}" class="az-nav-link {{ request()->routeIs('azari.admin.media.*') ? 'is-active' : '' }}">
                    <span class="material-symbols-outlined" aria-hidden="true">perm_media</span>
                    <span>Media</span>
                </a>
            @endif

            @if (Route::has('azari.admin.navigation.index'))
                <a href="{{ route('azari.admin.navigation.index') }}" class="az-nav-link {{ request()->routeIs('azari.admin.navigation.*') ? 'is-active' : '' }}">
                    <span class="material-symbols-outlined" aria-hidden="true">account_tree</span>
                    <span>Navigation</span>
                </a>
            @endif

            @if (Route::has('azari.admin.settings.edit'))
                <a href="{{ route('azari.admin.settings.edit') }}" class="az-nav-link {{ request()->routeIs('azari.admin.settings.edit') ? 'is-active' : '' }}">
                    <span class="material-symbols-outlined" aria-hidden="true">palette</span>
                    <span>Branding</span>
                </a>
            @endif
        </section>

        <section class="az-nav-section" aria-labelledby="az-nav-business">
            <h2 id="az-nav-business">Business</h2>

            @if (Route::has('azari.admin.payments.index'))
                <a href="{{ route('azari.admin.payments.index') }}" class="az-nav-link {{ request()->routeIs('azari.admin.payments.*') ? 'is-active' : '' }}">
                    <span class="material-symbols-outlined" aria-hidden="true">payments</span>
                    <span>Payments</span>
                </a>
            @endif

            @if (Route::has('azari.admin.documents.index'))
                <a href="{{ route('azari.admin.documents.index') }}" class="az-nav-link {{ request()->routeIs('azari.admin.documents.*') ? 'is-active' : '' }}">
                    <span class="material-symbols-outlined" aria-hidden="true">description</span>
                    <span>Documents</span>
                </a>
            @endif

            @if (Route::has('azari.admin.reports.index'))
                <a href="{{ route('azari.admin.reports.index') }}" class="az-nav-link {{ request()->routeIs('azari.admin.reports.*') ? 'is-active' : '' }}">
                    <span class="material-symbols-outlined" aria-hidden="true">monitoring</span>
                    <span>Reports</span>
                </a>
            @endif
        </section>

        <section class="az-nav-section" aria-labelledby="az-nav-system">
            <h2 id="az-nav-system">System</h2>

            @if (Route::has('azari.admin.settings.integrations'))
                <a href="{{ route('azari.admin.settings.integrations') }}" class="az-nav-link {{ request()->routeIs('azari.admin.settings.integrations') ? 'is-active' : '' }}">
                    <span class="material-symbols-outlined" aria-hidden="true">hub</span>
                    <span>Integrations</span>
                </a>
            @endif

            @if (Route::has('azari.admin.audit.index'))
                <a href="{{ route('azari.admin.audit.index') }}" class="az-nav-link {{ request()->routeIs('azari.admin.audit.*') ? 'is-active' : '' }}">
                    <span class="material-symbols-outlined" aria-hidden="true">history</span>
                    <span>Audit log</span>
                </a>
            @endif

            @if (Route::has('azari.admin.system.index'))
                <a href="{{ route('azari.admin.system.index') }}" class="az-nav-link {{ request()->routeIs('azari.admin.system.*') ? 'is-active' : '' }}">
                    <span class="material-symbols-outlined" aria-hidden="true">settings</span>
                    <span>System settings</span>
                </a>
            @endif
        </section>
    @if (Route::has('azari.admin.s56.bookings.index'))
<a class="az-nav-link {{ request()->routeIs('azari.admin.s56.bookings.*') ? 'is-active' : '' }}" href="{{ route('azari.admin.s56.bookings.index') }}"><span class="material-symbols-outlined">event_note</span><span>Booking operations</span></a>
@endif
@if (Route::has('azari.admin.s56.calendar'))
<a class="az-nav-link {{ request()->routeIs('azari.admin.s56.calendar') ? 'is-active' : '' }}" href="{{ route('azari.admin.s56.calendar') }}"><span class="material-symbols-outlined">calendar_month</span><span>Availability calendar</span></a>
@endif

</nav>

    <div class="az-sidebar-footer">
        <span class="material-symbols-outlined" aria-hidden="true">verified_user</span>
        <div>
            <strong>Secure administration</strong>
            <span>Authorised staff only</span>
        </div>
    </div>
</aside>
