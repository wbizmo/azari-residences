<footer class="site-footer">
    <style>
        .azari-footer-store-row {
            display: grid;
            grid-template-columns: 1fr auto 1fr;
            align-items: center;
            gap: clamp(18px, 4vw, 42px);
            margin-top: clamp(36px, 5vw, 58px);
            padding: clamp(25px, 3vw, 34px) 0;
        }

        .azari-footer-store-row::before,
        .azari-footer-store-row::after {
            content: "";
            width: 100%;
            height: 1px;
        }

        .azari-footer-store-row::before {
            background: linear-gradient(
                90deg,
                transparent,
                rgba(255, 255, 255, .16)
            );
        }

        .azari-footer-store-row::after {
            background: linear-gradient(
                90deg,
                rgba(255, 255, 255, .16),
                transparent
            );
        }

        .azari-footer-play-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 8px;
            transition:
                transform .2s ease,
                opacity .2s ease;
        }

        .azari-footer-play-badge:hover {
            transform: translateY(-2px);
            opacity: .94;
        }

        .azari-footer-play-badge:focus-visible {
            outline: 2px solid #052058;
            outline-offset: 5px;
        }

        .azari-footer-play-badge img {
            display: block;
            width: auto;
            height: 54px;
            object-fit: contain;
        }

        @media (max-width: 600px) {
            .azari-footer-store-row {
                grid-template-columns: minmax(24px,1fr) auto minmax(24px,1fr);
                gap: 14px;
                margin-top: 32px;
                padding: 24px 0;
            }

            .azari-footer-play-badge img {
                height: 48px;
            }
        }

        /* AZARI PLAY BADGE CLICK FIX */
        .site-footer .azari-footer-store-row {
            position: relative;
            z-index: 20;
            isolation: isolate;
        }

        .site-footer .azari-footer-store-row::before,
        .site-footer .azari-footer-store-row::after {
            pointer-events: none !important;
        }

        .site-footer .azari-footer-play-badge {
            position: relative;
            z-index: 30;
            pointer-events: auto !important;
            cursor: pointer;
        }

        .site-footer .azari-footer-play-badge img {
            pointer-events: none;
        }

    </style>

    <div class="site-container">
        <div class="footer-primary">
            <div class="footer-brand">
                <x-brand-logo variant="footer" />

                <p>
                    Private, fully serviced hotels & residences managed with
                    dedicated support from booking through checkout.
                </p>
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

        <div
            class="azari-footer-store-row"
            aria-label="Resavar on Google Play"
        >
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
        </div>

        <div class="footer-legal">
            <span>&copy; {{ now()->year }} Resavar.</span>
            <span>Resavar Luxury Properties LTD.</span>
            <a href="{{ route('home') }}">Back home</a>
        </div>
    </div>
</footer>
