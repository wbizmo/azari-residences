<footer class="site-footer">
    <div class="site-container">
        <div class="footer-primary">
            <div class="footer-brand">
                <x-brand-logo variant="footer" />
                <p>Exceptional stays, everywhere, with dependable support from booking through checkout.</p>
            </div>

            <div class="footer-column">
                <h2>Explore</h2>
                <a href="{{ route('public.apartments') }}">Apartments</a>
                <a href="{{ route('public.rooms') }}">Rooms</a>
                <a href="{{ route('availability.index') }}">Availability</a>
                <a href="{{ route('public.concierge') }}">Services</a>
                <a href="{{ route('public.local-guide') }}">Local guide</a>
            </div>

            <div class="footer-column">
                <h2>Guest support</h2>
                <a href="{{ route('bookings.verify') }}">Verify booking</a>
                <a href="{{ route('login') }}">Guest login</a>
                <a href="{{ route('register') }}">Create account</a>
                @if(Route::has('public.list-property'))
                    <a href="{{ route('public.list-property') }}">List my property</a>
                @endif
                <a href="{{ route('public.contact') }}">Contact Resavar</a>
            </div>

            <div class="footer-column">
                <h2>Policies</h2>
                <a href="{{ route('public.booking-terms') }}">Booking terms</a>
                <a href="{{ route('public.cancellation-policy') }}">Cancellation policy</a>
                <a href="{{ route('public.privacy-policy') }}">Privacy policy</a>
                <a href="{{ route('public.terms') }}">Terms and conditions</a>
            </div>
        </div>

        <div class="azari-footer-store-row" aria-label="Resavar on Google Play">
            <span class="azari-footer-store-line" aria-hidden="true"></span>
            <a
                href="https://play.google.com/store/apps/details?id=com.azariresidences.app"
                class="azari-footer-play-badge"
                target="_blank"
                rel="noopener noreferrer"
                aria-label="Get Resavar on Google Play"
            >
                <img
                    src="{{ asset('images/google-play-badge.png') }}"
                    alt="Get it on Google Play"
                    width="646"
                    height="250"
                    loading="lazy"
                    decoding="async"
                >
            </a>
            <span class="azari-footer-store-line" aria-hidden="true"></span>
        </div>

        <div class="footer-legal">
            <span>&copy; {{ now()->year }} Resavar.</span>
            <span>Resavar Luxury Properties LTD.</span>
            <a href="{{ route('home') }}">Back home</a>
        </div>
    </div>
</footer>
