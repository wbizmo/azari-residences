<footer class="site-footer">
    <div class="site-container">
        <div class="footer-primary">
            <div class="footer-brand">
                <x-brand-logo variant="footer" />
                <p>Private, fully serviced hotels & residences managed with dedicated support from booking through checkout.</p>
            </div>

            <div class="footer-column">
                <h2>Explore</h2>
                <a href="{{ route('user.owner.listings.create') }}">Apartments</a>
                <a href="{{ route('user.owner.listings.create') }}">Rooms</a>
                <a href="{{ route('user.owner.listings.create') }}">Availability</a>
                <a href="{{ route('user.owner.listings.create') }}">Services</a>
                <a href="{{ route('user.owner.listings.create') }}">Local guide</a>
            </div>

            <div class="footer-column">
                <h2>Guest support</h2>
                <a href="{{ route('user.owner.listings.create') }}">Verify booking</a>
                <a href="{{ route('user.owner.listings.create') }}">Guest login</a>
                <a href="{{ route('user.owner.listings.create') }}">Create account</a>
                <a href="{{ route('user.owner.listings.create') }}">List my property</a>

            <!-- AZARI PWA FOOTER LINK START -->
            <a
                href="/login"
                data-azari-pwa-install
                aria-label="Download the Azari App"
            >Download the Azari App</a>
            <!-- AZARI PWA FOOTER LINK END -->

            <a href="{{ route('public.contact') }}">Contact Azari</a>
            </div>

            <div class="footer-column">
                <h2>Policies</h2>
                <a href="{{ route('public.booking-terms') }}">Booking terms</a>
                <a href="{{ route('public.cancellation-policy') }}">Cancellation policy</a>
                <a href="{{ route('public.privacy-policy') }}">Privacy policy</a>
                <a href="{{ route('public.terms') }}">Terms and conditions</a>
            </div>
        </div>

        <div class="footer-legal">
            <span>&copy; {{ now()->year }} Azari Hotels & Residences.</span>
            <span>Azari Luxury Properties LTD.</span>
            <a href="{{ route('home') }}">Back home</a>
        </div>
    </div>
</footer>
