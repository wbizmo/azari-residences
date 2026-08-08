@php
    $contactEmail = 'hello@' . preg_replace('/^www\./i', '', request()->getHost());
@endphp

<style>
    /*
     * Public mobile navigation drawer layout.
     * Inline Blade CSS: no npm run build required.
     */

    .az-public-mobile-drawer {
        display: flex !important;
        height: 100%;
        min-height: 0;
        max-height: 100dvh;
        flex-direction: column;
        overflow: hidden !important;
    }

    .az-public-mobile-drawer .drawer-header {
        position: relative;
        z-index: 3;
        flex: 0 0 auto;
    }

    /*
     * Only the main navigation links scroll.
     */
    .az-public-drawer-scroll {
        min-height: 0;
        flex: 1 1 auto;
        overflow-x: hidden;
        overflow-y: auto;
        overscroll-behavior: contain;
        scrollbar-width: thin;
        scrollbar-color: rgba(20, 57, 45, 0.38) transparent;
        -webkit-overflow-scrolling: touch;
    }

    .az-public-drawer-scroll::-webkit-scrollbar {
        width: 6px;
    }

    .az-public-drawer-scroll::-webkit-scrollbar-track {
        background: transparent;
    }

    .az-public-drawer-scroll::-webkit-scrollbar-thumb {
        border-radius: 999px;
        background: rgba(20, 57, 45, 0.38);
    }

    .az-public-drawer-scroll .mobile-navigation {
        display: flex;
        min-height: min-content;
        flex-direction: column;
        padding: 12px 20px 22px;
    }

    .az-public-drawer-scroll .mobile-navigation a {
        flex: 0 0 auto;
    }

    /*
     * Fixed login/dashboard link above the assistance section.
     */
    .az-public-drawer-account {
        position: relative;
        z-index: 3;
        flex: 0 0 auto;
        padding: 16px 20px;
        border-top: 1px solid rgba(20, 57, 45, 0.12);
        background: #ffffff;
    }

    .az-public-drawer-account__action {
        display: inline-flex;
        align-items: center;
        gap: 7px;
        color: #14392d;
        font-size: 13px;
        font-weight: 700;
        text-decoration: none;
        transition:
            gap 180ms ease,
            opacity 180ms ease;
    }

    .az-public-drawer-account__action:hover,
    .az-public-drawer-account__action:focus-visible {
        gap: 10px;
        opacity: 0.78;
    }

    .az-public-drawer-account__text {
        text-decoration: underline;
        text-decoration-thickness: 1px;
        text-underline-offset: 4px;
    }

    .az-public-drawer-account__arrow {
        flex: 0 0 auto;
        font-size: 18px;
        text-decoration: none !important;
    }

    /*
     * Fixed assistance area at the bottom.
     */
    .az-public-drawer-assistance {
        position: relative;
        z-index: 3;
        flex: 0 0 auto;
        padding: 16px 20px calc(16px + env(safe-area-inset-bottom));
        border-top: 1px solid rgba(20, 57, 45, 0.12);
        background: #f6f3eb;
    }

    .az-public-drawer-assistance__label {
        display: flex;
        align-items: center;
        gap: 8px;
        margin-bottom: 6px;
        color: #14392d;
        font-size: 13px;
        font-weight: 700;
    }

    .az-public-drawer-assistance__label .material-symbols-outlined {
        flex: 0 0 auto;
        font-size: 20px;
    }

    .az-public-drawer-assistance p {
        margin: 0 0 8px;
        color: rgba(20, 57, 45, 0.72);
        font-size: 12px;
        line-height: 1.5;
    }

    .az-public-drawer-assistance a {
        display: inline-flex;
        max-width: 100%;
        align-items: center;
        gap: 7px;
        color: #14392d;
        font-size: 13px;
        font-weight: 700;
        overflow-wrap: anywhere;
        text-decoration: none;
        word-break: break-word;
    }

    .az-public-drawer-assistance a:hover,
    .az-public-drawer-assistance a:focus-visible {
        text-decoration: underline;
        text-underline-offset: 3px;
    }

    .az-public-drawer-assistance a .material-symbols-outlined {
        flex: 0 0 auto;
        font-size: 19px;
    }

    /*
     * Prevent the drawer from exceeding narrow phone viewports.
     */
    @media (max-width: 480px) {
        .az-public-drawer-scroll .mobile-navigation,
        .az-public-drawer-account,
        .az-public-drawer-assistance {
            padding-right: 16px;
            padding-left: 16px;
        }
    }
</style>

<div
    class="drawer-layer"
    id="mobile-navigation"
    data-drawer
    hidden
    aria-hidden="true"
>
    <div
        class="drawer-backdrop"
        data-drawer-close
    ></div>

    <aside
        class="drawer-panel az-public-mobile-drawer"
        role="dialog"
        aria-modal="true"
        aria-label="Mobile navigation"
    >
        <div class="drawer-header">
            <x-brand-logo :dark="true" />

            <button
                type="button"
                class="modal-close"
                data-drawer-close
                aria-label="Close navigation"
            >
                <span
                    class="material-symbols-outlined"
                    aria-hidden="true"
                >
                    close
                </span>
            </button>
        </div>

        {{-- Independently scrolling navigation links --}}
        <div class="az-public-drawer-scroll">
            <nav
                class="mobile-navigation"
                aria-label="Mobile navigation links"
            >
                <a href="{{ route('home') }}">Home</a>
                <a href="{{ route('public.apartments') }}">Apartments</a>
                <a href="{{ route('public.rooms') }}">Rooms</a>
                <a href="{{ route('availability.index') }}">Check availability</a>
                <a href="{{ route('public.services') }}">Services</a>
                <a href="{{ route('public.concierge') }}">Concierge</a>
                <a href="{{ route('public.housekeeping') }}">Housekeeping</a>
                <a href="{{ route('public.restaurant') }}">Restaurant</a>
                <a href="{{ route('public.airport-transfers') }}">Airport transfers</a>
                <!-- <a href="{{ route('user.owner.listings.create') }}">List my property</a> -->
                <a href="{{ route('public.local-guide') }}">Local guide</a>
                <a href="{{ route('public.about') }}">About Azari</a>

                <a href="{{ route('public.contact') }}">Contact</a>
                @if(Route::has('public.list-property'))
                    <a href="{{ route('user.owner.listings.create') }}">List your property</a>
                @endif

                <a href="{{ route('bookings.verify') }}">Verify booking</a>

                <a href="{{ route('public.book-now') }}">Book now</a>
            </nav>
        </div>

        {{-- Fixed login or dashboard action --}}
        <div class="az-public-drawer-account">
            @auth
                <a
                    href="{{ auth()->user()->isStaff() ? route('azari.admin.dashboard') : route('user.dashboard') }}"
                    class="az-public-drawer-account__action"
                >
                    <span class="az-public-drawer-account__text">
                        Dashboard
                    </span>

                    <span
                        class="material-symbols-outlined az-public-drawer-account__arrow"
                        aria-hidden="true"
                    >
                        arrow_forward
                    </span>
                </a>
            @else
                <a
                    href="{{ route('login') }}"
                    class="az-public-drawer-account__action"
                >
                    <span class="az-public-drawer-account__text">
                        Login
                    </span>

                    <span
                        class="material-symbols-outlined az-public-drawer-account__arrow"
                        aria-hidden="true"
                    >
                        arrow_forward
                    </span>
                </a>
            @endauth
        </div>

        {{-- Fixed dynamic assistance email --}}
        @if ($contactEmail)
            <div class="az-public-drawer-assistance">
                <div class="az-public-drawer-assistance__label">
                    <span
                        class="material-symbols-outlined"
                        aria-hidden="true"
                    >
                        support_agent
                    </span>

                    <span>Need assistance?</span>
                </div>

                <p>
                    Contact the Azari Hotels & Residences guest-support team.
                </p>

                <a href="mailto:{{ $contactEmail }}">
                    <span
                        class="material-symbols-outlined"
                        aria-hidden="true"
                    >
                        mail
                    </span>

                    <span>{{ $contactEmail }}</span>
                </a>
            </div>
        @endif
    </aside>
</div>