<footer class="site-footer" id="contact">
    <div class="site-container">
        <div class="footer-primary">
            <div class="footer-brand">
                
                    <img class="footer-brand-image" src="{{ $siteSettings['logo_url'] }}" alt="{{ $siteSettings['site_name'] ?? 'Azari Residences' }}">
                
                    

        <x-brand-logo variant="footer" />

    
                
                <p>
                    Private, fully serviced residences across Lagos, managed with
                    dedicated support from booking through checkout.
                </p>
            </div>

            <div class="footer-column">
                <h2>Explore</h2>
                <a href="{{ route('home') }}#residences">Apartments</a>
                <a href="{{ route('home') }}#residences">Rooms</a>
                <a href="{{ route('home') }}#availability">Availability</a>
                <a href="{{ route('home') }}#services">Services</a>
            </div>

            <div class="footer-column">
                <h2>Guest support</h2>
                <button type="button" data-modal-open="verification-modal">Verify booking</button>
                <a href="{{ route('login') }}">Guest login</a>
                <a href="{{ route('register') }}">Create account</a>
                <a href="mailto:hello@example.com">Contact support</a>
            </div>

            <div class="footer-column">
                <h2>Policies</h2>
                <a href="#booking-terms">Booking terms</a>
                <a href="#cancellation-policy">Cancellation policy</a>
                <a href="#privacy-policy">Privacy policy</a>
                <a href="#terms">Terms and conditions</a>
            </div>
        </div>

        <div class="footer-newsletter">
            <div>
                <span class="eyebrow">Private invitations</span>
                <h2>Stay close to new residences and seasonal offers.</h2>
            </div>

            <form class="newsletter-form" data-demo-form data-success-message="Thank you. Your interest has been recorded for the newsletter sprint.">
                <label class="sr-only" for="newsletter-email">Email address</label>
                <input
                    class="luxury-input"
                    id="newsletter-email"
                    name="email"
                    type="email"
                    placeholder="Email address"
                    required
                >
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
