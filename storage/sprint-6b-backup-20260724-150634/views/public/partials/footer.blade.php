<footer class="site-footer">
    <div class="site-container">
        <div class="footer-primary">
            <div class="footer-brand">
                <x-brand-logo variant="footer" />
                <p>Private, fully serviced residences managed with dedicated support from booking through checkout.</p>
            </div>

            <div class="footer-column">
                <h2>Explore</h2>
                <a href="{{ route('public.apartments') }}">Apartments</a>
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
                <a href="{{ route('public.support') }}">Contact support</a>
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

        <div class="footer-newsletter">
            <div>
                <span class="eyebrow">Private invitations</span>
                <h2>Stay close to new residences and seasonal offers.</h2>
            </div>
            <form class="newsletter-form" data-demo-form
                  data-success-message="Thank you. Your interest has been recorded.">
                <label class="sr-only" for="newsletter-email">Email address</label>
                <input class="luxury-input" id="newsletter-email" name="email"
                       type="email" placeholder="Email address" required>
                <button class="button button-brass" type="submit">Join the list</button>
            </form>
        </div>

        <div class="footer-legal">
            <span>&copy; {{ now()->year }} Azari Residences.</span>
            <span>Azari Luxury Properties LTD.</span>
            <a href="{{ route('home') }}">Back home</a>
        </div>
    </div>
</footer>
