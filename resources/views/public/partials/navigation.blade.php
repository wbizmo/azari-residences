<header class="site-header" data-site-header>
    <div class="site-container nav-shell">
        <a href="{{ route('home') }}" class="brand azari-brand azari-brand--header azari-header-logo" aria-label="{{ config('app.name', 'Resavar') }} home">

        <span class="brand-logo-slot azari-brand__logo-slot azari-header-logo__slot">
                <img
                    src="{{ asset('images/resavar-logo-dark.png') }}?v=20261004-4"
                    data-azari-public-logo
                    data-dark-logo="{{ asset('images/resavar-logo-dark.png') }}?v=20261004-4"
                    data-light-logo="{{ asset('images/resavar-logo-light.png') }}?v=20261004-4"
                    alt="{{ config('app.name', 'Resavar') }}"
                    class="brand-image azari-brand__image azari-header-logo__image"
                    loading="eager"
                    decoding="async"
                >
            </span>

        </a>

        <nav class="desktop-navigation" aria-label="Primary navigation">
            <a href="{{ route('public.apartments') }}">Apartments</a>
            <a href="{{ route('public.rooms') }}">Rooms</a>
            <a href="{{ route('availability.index') }}">Availability</a>

            <div class="nav-popover" data-popover>
                <button type="button" class="nav-popover-trigger" data-popover-trigger
                        aria-expanded="false" aria-controls="services-popover">
                    Services
                    <span class="material-symbols-outlined" aria-hidden="true">keyboard_arrow_down</span>
                </button>

                <div class="nav-popover-panel" id="services-popover" data-popover-panel hidden>
                    @if(Route::has('public.list-property'))
                        <a href="{{ route('public.list-property') }}"
                           class="{{ request()->routeIs('public.list-property') ? 'is-active' : '' }}">
                            <span class="material-symbols-outlined" aria-hidden="true">add_home_work</span>
                            <span>List my property</span>
                        </a>
                    @endif
                    <a href="{{ route('public.concierge') }}" class="{{ request()->routeIs('public.concierge') ? 'is-active' : '' }}">
                        <span class="material-symbols-outlined">concierge</span>Concierge
                    </a>
                    <a href="{{ route('public.housekeeping') }}" class="{{ request()->routeIs('public.housekeeping') ? 'is-active' : '' }}">
                        <span class="material-symbols-outlined">cleaning_services</span>Housekeeping
                    </a>
                    <a href="{{ route('public.restaurant') }}" class="{{ request()->routeIs('public.restaurant') ? 'is-active' : '' }}">
                        <span class="material-symbols-outlined">restaurant</span>Restaurant
                    </a>
                    <a href="{{ route('public.airport-transfers') }}" class="{{ request()->routeIs('public.airport-transfers') ? 'is-active' : '' }}">
                        <span class="material-symbols-outlined">airport_shuttle</span>Airport transfers
                    </a>
                </div>
            </div>

            <a href="{{ route('public.local-guide') }}">Local guide</a>
            <a href="{{ route('public.about') }}">About</a>

            <a href="{{ route('public.contact') }}">Contact</a>

        </nav>


        <div class="nav-actions">
            <a href="{{ route('bookings.verify') }}" class="nav-text-action">Verify booking</a>

            @auth
                <a href="{{ auth()->user()->isStaff() ? route('azari.admin.dashboard') : route('user.dashboard') }}" class="nav-text-action">Dashboard</a>
            @else
                <a href="{{ route('login') }}" class="nav-text-action">Login</a>
            @endauth

            <a href="{{ route('public.book-now') }}" class="button button-brass">Book now</a>

            <button
                type="button"
                class="mobile-menu-button azari-menu-toggle"
                data-drawer-open="mobile-navigation"
                data-azari-menu-toggle
                aria-label="Open navigation"
                aria-expanded="false"
            >
                <span class="azari-menu-toggle__icon" aria-hidden="true">
                    <span class="azari-menu-toggle__line azari-menu-toggle__line--top"></span>
                    <span class="azari-menu-toggle__line azari-menu-toggle__line--middle"></span>
                    <span class="azari-menu-toggle__line azari-menu-toggle__line--bottom"></span>
                </span>
            </button>
        </div>
    </div>
</header>
