<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    @include('partials.material-symbols-preload')
    @include('partials.azari-head-assets')

    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>
        @yield('title', 'Guest area') |
        {{ $siteSettings['site_name'] ?? 'Resavar' }}
    </title>

    @vite([
        'resources/css/app.css',
        'resources/js/app.js'
    ])

    @stack('head')

    @include("partials.azari-shell-layout")

</head>

<body class="az-user-body">
<div class="az-user-shell">

    {{-- =====================================================
         DESKTOP SIDEBAR
         ===================================================== --}}
    <aside
        class="az-user-sidebar"
        aria-label="Guest account navigation"
    >
        <div class="az-user-brand-area">
            <a
                href="{{ route('user.dashboard') }}"
                class="brand brand-dark azari-brand azari-brand--guest-sidebar"
                aria-label="Resavar guest dashboard"
            >
                <span class="brand-logo-slot azari-brand__logo-slot guest-brand-logo-slot">
                    <img
                        src="{{ asset('images/resavar-logo-dark.png') }}"
                        alt="Resavar"
                        class="brand-image azari-brand__image guest-brand-image"
                        loading="eager"
                        decoding="async"
                    >
                </span>
            </a>
        </div>

        {{-- Independently scrolling navigation --}}
        <div class="az-user-sidebar-scroll">
            @include('user.partials.navigation')
        </div>

        {{-- Fixed logout action --}}
        <div class="az-user-logout-fixed">
            <form
                method="POST"
                action="{{ route('logout') }}"
                class="az-user-logout-form"
            >
                @csrf

                <button
                    type="submit"
                    class="az-user-logout-button"
                >
                    <span
                        class="material-symbols-outlined az-user-logout-icon"
                        aria-hidden="true"
                    >
                        logout
                    </span>

                    <span class="az-user-logout-text">
                        Logout
                    </span>

                    <span
                        class="material-symbols-outlined az-user-logout-arrow"
                        aria-hidden="true"
                    >
                        arrow_forward
                    </span>
                </button>
            </form>
        </div>

        {{-- Fixed assistance card --}}
        <div class="az-user-support-fixed">
            @include('user.partials.support-card')
        </div>
    </aside>

    {{-- =====================================================
         MOBILE DRAWER
         ===================================================== --}}
    <div
        class="az-user-mobile-backdrop"
        data-user-drawer-close
    ></div>

    <aside
        class="az-user-mobile-drawer"
        aria-label="Mobile guest navigation"
        aria-hidden="true"
    >
        <div class="az-user-drawer-header">
            <div>
                <a
                    href="{{ route('user.dashboard') }}"
                    class="brand brand-dark azari-brand azari-brand--guest-sidebar"
                    aria-label="Resavar guest dashboard"
                >
                    <span class="brand-logo-slot azari-brand__logo-slot guest-brand-logo-slot">
                        <img
                            src="{{ asset('images/resavar-logo-dark.png') }}"
                            alt="Resavar"
                            class="brand-image azari-brand__image guest-brand-image"
                            loading="eager"
                            decoding="async"
                        >
                    </span>
                </a>
            </div>

            <button
                class="az-user-drawer-close"
                type="button"
                data-user-drawer-close
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

        {{-- Independently scrolling navigation --}}
        <div class="az-user-drawer-scroll">
            @include('user.partials.navigation')
        </div>

        {{-- Fixed mobile logout action --}}
        <div class="az-user-logout-fixed">
            <form
                method="POST"
                action="{{ route('logout') }}"
                class="az-user-logout-form"
            >
                @csrf

                <button
                    type="submit"
                    class="az-user-logout-button"
                >
                    <span
                        class="material-symbols-outlined az-user-logout-icon"
                        aria-hidden="true"
                    >
                        logout
                    </span>

                    <span class="az-user-logout-text">
                        Logout
                    </span>

                    <span
                        class="material-symbols-outlined az-user-logout-arrow"
                        aria-hidden="true"
                    >
                        arrow_forward
                    </span>
                </button>
            </form>
        </div>

        {{-- Fixed mobile assistance card --}}
        <div class="az-user-support-fixed">
            @include('user.partials.support-card')
        </div>
    </aside>

    {{-- =====================================================
         MAIN CONTENT
         ===================================================== --}}
    <main class="az-user-main">
        <header class="az-user-topbar">
            <div class="az-user-topbar-left">
                <button
                    class="az-user-menu-button"
                    type="button"
                    data-user-drawer-open
                    aria-expanded="false"
                    aria-label="Open guest navigation"
                >
                    <span
                        class="material-symbols-outlined"
                        aria-hidden="true"
                    >
                        menu
                    </span>
                </button>

                <div>
                    <div class="az-user-kicker">
                        @yield('kicker', 'Guest area')
                    </div>

                    <h1 class="az-user-page-title">
                        @yield('page_title', 'Your Resavar stay')
                    </h1>
                </div>
            </div>

            <a
                class="az-user-profile-chip"
                href="{{ route('user.profile.edit') }}"
                aria-label="Open profile"
            >
                <span class="az-user-avatar">
                    @if (auth()->user()->profile_photo_path)
                        <img
                            src="{{ \Storage::url(auth()->user()->profile_photo_path) }}"
                            alt=""
                        >
                    @else
                        {{
                            collect(explode(' ', auth()->user()->name))
                                ->map(fn ($name) => mb_substr($name, 0, 1))
                                ->take(2)
                                ->implode('')
                        }}
                    @endif
                </span>

                <span class="az-user-profile-meta">
                    <strong>
                        {{ auth()->user()->name }}
                    </strong>

                    <span>
                        {{
                            auth()->user()->currentIdentity()->exists()
                                ? 'Identity on file'
                                : 'Guest account'
                        }}
                    </span>
                </span>
            </a>
        </header>

        <div class="az-user-content">
            @if (session('success'))
                <div class="az-user-flash az-user-flash--success">
                    <span
                        class="material-symbols-outlined"
                        aria-hidden="true"
                    >
                        check_circle
                    </span>

                    <span>
                        {{ session('success') }}
                    </span>
                </div>
            @endif

            @if (session('warning'))
                <div class="az-user-flash az-user-flash--warning">
                    <span
                        class="material-symbols-outlined"
                        aria-hidden="true"
                    >
                        warning
                    </span>

                    <span>
                        {{ session('warning') }}
                    </span>
                </div>
            @endif

            @if (session('error'))
                <div class="az-user-flash az-user-flash--error">
                    <span
                        class="material-symbols-outlined"
                        aria-hidden="true"
                    >
                        error
                    </span>

                    <span>
                        {{ session('error') }}
                    </span>
                </div>
            @endif

            @if ($errors->any())
                <div class="az-user-flash az-user-flash--error">
                    <span
                        class="material-symbols-outlined"
                        aria-hidden="true"
                    >
                        error
                    </span>

                    <div>
                        <strong>Please review the form.</strong>

                        <ul class="az-user-errors">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            @endif

            @yield('content')
        </div>
    </main>

    {{-- =====================================================
         MOBILE BOTTOM NAVIGATION
         ===================================================== --}}
    <nav
        class="az-user-mobile-bottom"
        aria-label="Guest quick navigation"
    >
        <a
            class="{{ request()->routeIs('user.dashboard') ? 'is-active' : '' }}"
            href="{{ route('user.dashboard') }}"
        >
            <span
                class="material-symbols-outlined"
                aria-hidden="true"
            >
                space_dashboard
            </span>

            <span>Home</span>
        </a>

        <a
            class="{{ request()->routeIs('user.bookings.*') ? 'is-active' : '' }}"
            href="{{ route('user.bookings.index') }}"
        >
            <span
                class="material-symbols-outlined"
                aria-hidden="true"
            >
                calendar_month
            </span>

            <span>Bookings</span>
        </a>

        <a
            class="{{ request()->routeIs('user.payments.*') ? 'is-active' : '' }}"
            href="{{ route('user.payments.index') }}"
        >
            <span
                class="material-symbols-outlined"
                aria-hidden="true"
            >
                account_balance_wallet
            </span>

            <span>Payments</span>
        </a>

        <a
            class="{{ request()->routeIs('user.identity.*') ? 'is-active' : '' }}"
            href="{{ route('user.identity.index') }}"
        >
            <span
                class="material-symbols-outlined"
                aria-hidden="true"
            >
                badge
            </span>

            <span>Identity</span>
        </a>

        <a
            class="{{ request()->routeIs('user.profile.*') ? 'is-active' : '' }}"
            href="{{ route('user.profile.edit') }}"
        >
            <span
                class="material-symbols-outlined"
                aria-hidden="true"
            >
                person
            </span>

            <span>Profile</span>
        </a>
    </nav>
</div>

<style>
    /* =========================================================
       FIXED LOGOUT ACTION
       Icon, text, underline and arrow are all red.
       ========================================================= */

    .az-user-logout-fixed {
        position: relative;
        z-index: 3;
        flex: 0 0 auto;
        padding: 13px 18px;
        border-top: 1px solid rgba(255, 255, 255, 0.12);
        background: var(--az-user-sidebar, #0b2a22);
    }

    .az-user-logout-form {
        width: 100%;
        margin: 0;
    }

    .az-user-logout-button {
        display: flex;
        width: 100%;
        min-width: 0;
        align-items: center;
        gap: 9px;

        margin: 0;
        padding: 9px 10px;

        border: 0;
        border-radius: 9px;
        background: transparent;
        color: #ef4444 !important;

        font: inherit;
        font-size: 13px;
        font-weight: 700;
        line-height: 1.3;
        text-align: left;

        cursor: pointer;
        transition:
            gap 180ms ease,
            color 180ms ease,
            background-color 180ms ease,
            opacity 180ms ease;
    }

    .az-user-logout-button,
    .az-user-logout-button .az-user-logout-icon,
    .az-user-logout-button .az-user-logout-text,
    .az-user-logout-button .az-user-logout-arrow {
        color: #ef4444 !important;
    }

    .az-user-logout-button:hover,
    .az-user-logout-button:focus-visible {
        gap: 12px;
        background: rgba(239, 68, 68, 0.12);
        color: #f87171 !important;
        outline: none;
    }

    .az-user-logout-button:hover .az-user-logout-icon,
    .az-user-logout-button:hover .az-user-logout-text,
    .az-user-logout-button:hover .az-user-logout-arrow,
    .az-user-logout-button:focus-visible .az-user-logout-icon,
    .az-user-logout-button:focus-visible .az-user-logout-text,
    .az-user-logout-button:focus-visible .az-user-logout-arrow {
        color: #f87171 !important;
    }

    .az-user-logout-button:focus-visible {
        box-shadow: 0 0 0 2px rgba(239, 68, 68, 0.38);
    }

    .az-user-logout-icon {
        flex: 0 0 auto;
        color: #ef4444 !important;
        font-size: 20px;
        line-height: 1;
    }

    .az-user-logout-text {
        min-width: 0;
        flex: 1 1 auto;
        color: #ef4444 !important;
        text-decoration: underline;
        text-decoration-color: #ef4444 !important;
        text-decoration-thickness: 1px;
        text-underline-offset: 4px;
    }

    .az-user-logout-button:hover .az-user-logout-text,
    .az-user-logout-button:focus-visible .az-user-logout-text {
        text-decoration-color: #f87171 !important;
    }

    .az-user-logout-arrow {
        flex: 0 0 auto;
        color: #ef4444 !important;
        font-size: 18px;
        line-height: 1;
        text-decoration: none !important;
    }

    /* =========================================================
       LIVE USER MOBILE DRAWER FIX
       Uses the actual Resavar Blade markup and body state.
       ========================================================= */

    @media (max-width: 980px) {
        /*
         * Hide the permanent desktop sidebar on mobile.
         */
        .az-user-sidebar {
            display: none !important;
        }

        /*
         * Drawer is hidden off-screen by default.
         */
        .az-user-mobile-drawer {
            position: fixed !important;
            z-index: 10020 !important;
            top: 0 !important;
            bottom: 0 !important;
            left: 0 !important;

            display: flex !important;
            width: min(88vw, 340px) !important;
            max-width: 340px !important;
            height: 100dvh !important;
            min-height: 0 !important;
            flex-direction: column !important;

            overflow: hidden !important;
            transform: translate3d(-105%, 0, 0) !important;
            visibility: hidden !important;
            pointer-events: none !important;

            box-shadow: 24px 0 70px rgba(0, 0, 0, 0.34) !important;

            transition:
                transform 220ms ease,
                visibility 220ms ease !important;
        }

        /*
         * The existing JS adds az-user-drawer-open to the body.
         */
        body.az-user-drawer-open .az-user-mobile-drawer {
            transform: translate3d(0, 0, 0) !important;
            visibility: visible !important;
            pointer-events: auto !important;
        }

        /*
         * Actual backdrop class used in this layout.
         */
        .az-user-mobile-backdrop {
            position: fixed !important;
            z-index: 10010 !important;
            inset: 0 !important;

            display: block !important;
            border: 0 !important;
            background: rgba(5, 20, 15, 0.68) !important;

            opacity: 0 !important;
            visibility: hidden !important;
            pointer-events: none !important;

            backdrop-filter: blur(3px);
            -webkit-backdrop-filter: blur(3px);

            transition:
                opacity 220ms ease,
                visibility 220ms ease !important;
        }

        body.az-user-drawer-open .az-user-mobile-backdrop {
            opacity: 1 !important;
            visibility: visible !important;
            pointer-events: auto !important;
        }

        /*
         * Keep the drawer above bottom navigation and page elements.
         */
        .az-user-mobile-bottom {
            z-index: 90 !important;
        }

        /*
         * Lock page scrolling while drawer is open.
         */
        body.az-user-drawer-open {
            overflow: hidden !important;
            touch-action: none;
        }

        /*
         * Drawer header and close button.
         */
        .az-user-drawer-header {
            position: relative;
            z-index: 3;

            display: flex !important;
            min-height: 82px;
            flex: 0 0 auto;
            align-items: center;
            justify-content: space-between;
            gap: 14px;

            padding: 16px 18px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.12);
            background: var(--az-user-sidebar, #0b2a22);
        }

        .az-user-drawer-close {
            display: inline-grid !important;
            width: 42px !important;
            height: 42px !important;
            flex: 0 0 42px;
            place-items: center;

            padding: 0 !important;
            border: 1px solid rgba(255, 255, 255, 0.18) !important;
            border-radius: 10px !important;
            background: rgba(255, 255, 255, 0.08) !important;
            color: #ffffff !important;

            cursor: pointer;
            -webkit-tap-highlight-color: transparent;
        }

        .az-user-drawer-close:hover,
        .az-user-drawer-close:focus-visible {
            border-color: rgba(255, 255, 255, 0.34) !important;
            background: rgba(255, 255, 255, 0.16) !important;
        }

        .az-user-drawer-close .material-symbols-outlined {
            display: inline-block !important;
            color: #ffffff !important;
            font-size: 25px !important;
            line-height: 1 !important;
            visibility: visible !important;
            opacity: 1 !important;
        }

        /*
         * Only the menu area scrolls.
         */
        .az-user-drawer-scroll {
            min-height: 0 !important;
            flex: 1 1 auto !important;
            overflow-x: hidden !important;
            overflow-y: auto !important;
            overscroll-behavior: contain;
            -webkit-overflow-scrolling: touch;
        }

        /*
         * Logout stays fixed between menu and support card.
         */
        .az-user-mobile-drawer .az-user-logout-fixed {
            position: relative;
            z-index: 3;
            flex: 0 0 auto;
            padding: 10px 12px;
            border-top: 1px solid rgba(255, 255, 255, 0.12);
            background: var(--az-user-sidebar, #0b2a22);
        }

        /*
         * Assistance card stays fixed below logout.
         */
        .az-user-mobile-drawer .az-user-support-fixed {
            position: relative;
            z-index: 3;
            flex: 0 0 auto;
            padding: 12px;
            border-top: 1px solid rgba(255, 255, 255, 0.12);
            background: var(--az-user-sidebar, #0b2a22);
        }
    }

    @media (min-width: 981px) {
        /*
         * Desktop keeps the permanent sidebar.
         */
        .az-user-sidebar {
            display: flex !important;
        }

        .az-user-mobile-drawer,
        .az-user-mobile-backdrop {
            display: none !important;
        }
    }

    @media (max-width: 480px) {
        .az-user-mobile-drawer {
            width: min(92vw, 340px) !important;
        }
    }

    @media (prefers-reduced-motion: reduce) {
        .az-user-mobile-drawer,
        .az-user-mobile-backdrop,
        .az-user-logout-button {
            transition: none !important;
        }
    }
</style>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const body = document.body;
        const drawer = document.querySelector('.az-user-mobile-drawer');
        const openButton = document.querySelector('[data-user-drawer-open]');
        const closeButtons = document.querySelectorAll('[data-user-drawer-close]');

        if (!drawer || !openButton) {
            return;
        }

        function openUserDrawer() {
            body.classList.add('az-user-drawer-open');
            openButton.setAttribute('aria-expanded', 'true');
            drawer.setAttribute('aria-hidden', 'false');

            const closeButton = drawer.querySelector('.az-user-drawer-close');

            window.requestAnimationFrame(function () {
                closeButton?.focus();
            });
        }

        function closeUserDrawer() {
            body.classList.remove('az-user-drawer-open');
            openButton.setAttribute('aria-expanded', 'false');
            drawer.setAttribute('aria-hidden', 'true');
        }

        openButton.addEventListener('click', openUserDrawer);

        closeButtons.forEach(function (button) {
            button.addEventListener('click', closeUserDrawer);
        });

        document.addEventListener('keydown', function (event) {
            if (
                event.key === 'Escape' &&
                body.classList.contains('az-user-drawer-open')
            ) {
                closeUserDrawer();
            }
        });

        window.addEventListener('resize', function () {
            if (window.innerWidth > 980) {
                closeUserDrawer();
            }
        });

        closeUserDrawer();
    });
</script>
</body>
</html>