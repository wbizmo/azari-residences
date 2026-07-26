<header class="site-header" data-site-header>
    <div class="site-container nav-shell">
        <x-brand-logo />

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
                    <a href="{{ route('public.concierge') }}">
                        <span class="material-symbols-outlined">concierge</span>Concierge
                    </a>
                    <a href="{{ route('public.housekeeping') }}">
                        <span class="material-symbols-outlined">cleaning_services</span>Housekeeping
                    </a>
                    <a href="{{ route('public.restaurant') }}">
                        <span class="material-symbols-outlined">restaurant</span>Restaurant
                    </a>
                    <a href="{{ route('public.airport-transfers') }}">
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

            <button type="button" class="mobile-menu-button"
                    data-drawer-open="mobile-navigation" aria-label="Open navigation">
                <span class="material-symbols-outlined" aria-hidden="true">menu</span>
            </button>
        </div>
    </div>
</header>
