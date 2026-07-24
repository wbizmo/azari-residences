<div class="drawer-layer" id="mobile-navigation" data-drawer hidden aria-hidden="true">
    <div class="drawer-backdrop" data-drawer-close></div>

    <aside class="drawer-panel" role="dialog" aria-modal="true" aria-label="Mobile navigation">
        <div class="drawer-header">
            <x-brand-logo :dark="true" />
            <button type="button" class="modal-close" data-drawer-close aria-label="Close navigation">
                <span class="material-symbols-outlined" aria-hidden="true">close</span>
            </button>
        </div>

        <nav class="mobile-navigation" aria-label="Mobile navigation">
            <a href="{{ route('home') }}">Home</a>
            <a href="{{ route('public.apartments') }}">Apartments</a>
            <a href="{{ route('public.rooms') }}">Rooms</a>
            <a href="{{ route('availability.index') }}">Check availability</a>
            <a href="{{ route('public.services') }}">Services</a>
            <a href="{{ route('public.concierge') }}">Concierge</a>
            <a href="{{ route('public.housekeeping') }}">Housekeeping</a>
            <a href="{{ route('public.restaurant') }}">Restaurant</a>
            <a href="{{ route('public.airport-transfers') }}">Airport transfers</a>
            <a href="{{ route('public.local-guide') }}">Local guide</a>
            <a href="{{ route('public.about') }}">About Azari</a>
            <a href="{{ route('public.contact') }}">Contact</a>
            <a href="{{ route('bookings.verify') }}">Verify booking</a>
        </nav>

        <div class="drawer-actions">
            @auth
                <a href="{{ route('dashboard') }}" class="button button-primary button-block">Guest dashboard</a>
            @else
                <a href="{{ route('login') }}" class="button button-secondary button-block">Login</a>
                <a href="{{ route('register') }}" class="button button-primary button-block">Create account</a>
            @endauth
        </div>
    </aside>
</div>
