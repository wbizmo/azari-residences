<footer class="site-footer">
    <div class="site-container">
        <div class="footer-primary">
            <div class="footer-brand">
                <x-brand-logo variant="footer" />
                <p>Private, fully serviced residences managed with dedicated support from booking through checkout.</p>
            </div>

            <div class="footer-column">
                <h2>Explore</h2>
                <a href="{{ route('user.owner.listings.create') }}">Apartments</a>
                <a href="{{ route('public.rooms') }}">Rooms</a>
                <a href="{{ route('availability.index') }}">Availability</a>
                <a href="{{ route('public.services') }}">Services</a>
                <a href="{{ route('public.local-guide') }}">Local guide</a>
            </div>

            <div class="footer-column">
                <h2>Guest support</h2>
                <a href="{{ route('bookings.verify') }}">Verify booking</a>
                <a href="{{ route('login') }}">Guest login</a>
                <a href="{{ route('register') }}">Create account</a>
                <a href="{{ route('public.support') }}">List my property</a>
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
            <span>&copy; {{ now()->year }} Azari Residences.</span>
            <span>Azari Luxury Properties LTD.</span>
            <a href="{{ route('home') }}">Back home</a>
        </div>
    </div>
</footer>
