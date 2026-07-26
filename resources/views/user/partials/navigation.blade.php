@php
$unread=auth()->user()->unreadNotifications()->count();
$contactEmail=\App\Models\SiteSetting::valueFor('customer_dashboard_contact_email',\App\Models\SiteSetting::valueFor('public_contact_email',config('mail.from.address')));
@endphp

<section class="az-user-nav-group">
    <a class="az-user-nav-link"
           href="{{ route('home') }}">
            <span class="material-symbols-outlined">language</span>
            <span>Public site</span>
    </a>
    <p class="az-user-nav-label">Stay</p>

    <nav class="az-user-nav-list">
        @foreach([
            ['user.dashboard','space_dashboard','Dashboard'],
            ['user.bookings.index','calendar_month','My bookings'],
            ['user.payments.index','account_balance_wallet','Payments'],
            ['user.documents.index','description','Receipts & invoices'],
            ['user.identity.index','badge','My identity'],
            ['user.guests.index','group','Additional guests']
        ] as [$route,$icon,$label])

            @if(Route::has($route))
                <a class="az-user-nav-link {{ request()->routeIs(str_replace('.index','.*',$route))?'is-active':'' }}"
                   href="{{ route($route) }}">
                    <span class="material-symbols-outlined">{{ $icon }}</span>
                    <span>{{ $label }}</span>
                </a>
            @endif

        @endforeach
    </nav>
</section>

<section class="az-user-nav-group">
    <p class="az-user-nav-label">Services</p>

    <nav class="az-user-nav-list">

        @if(Route::has('user.service-requests.index'))
            <a class="az-user-nav-link {{ request()->routeIs('user.service-requests.*')?'is-active':'' }}"
               href="{{ route('user.service-requests.index') }}">
                <span class="material-symbols-outlined">room_service</span>
                <span>Service requests</span>
            </a>
        @endif

        @if(Route::has('user.support.index'))
            <a class="az-user-nav-link {{ request()->routeIs('user.support.*')?'is-active':'' }}"
               href="{{ route('user.support.index') }}">
                <span class="material-symbols-outlined">support_agent</span>
                <span>Support tickets</span>
            </a>
        @endif

        @if(Route::has('user.notifications.index'))
            <a class="az-user-nav-link {{ request()->routeIs('user.notifications.*')?'is-active':'' }}"
               href="{{ route('user.notifications.index') }}">
                <span class="material-symbols-outlined">notifications</span>
                <span>Notifications</span>

                @if($unread)
                    <span class="az-user-nav-badge">{{ min($unread,99) }}</span>
                @endif
            </a>
        @endif

    </nav>
</section>

<section class="az-user-nav-group">
    <p class="az-user-nav-label">Account</p>

    <nav class="az-user-nav-list">

        @if(Route::has('user.profile.edit'))
            <a class="az-user-nav-link {{ request()->routeIs('user.profile.*')?'is-active':'' }}"
               href="{{ route('user.profile.edit') }}">
                <span class="material-symbols-outlined">person</span>
                <span>Profile</span>
            </a>
        @endif

        @if(Route::has('user.security.index'))
            <a class="az-user-nav-link {{ request()->routeIs('user.security.*')?'is-active':'' }}"
               href="{{ route('user.security.index') }}">
                <span class="material-symbols-outlined">shield</span>
                <span>Security</span>
            </a>
        @endif

        @if(Route::has('user.contact'))
            <a class="az-user-nav-link {{ request()->routeIs('user.contact')?'is-active':'' }}"
               href="{{ route('user.contact') }}">
                <span class="material-symbols-outlined">mail</span>
                <span>Contact Azari</span>
            </a>
        @endif

    </nav>
</section>