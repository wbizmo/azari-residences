<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    @include('partials.material-symbols-preload')
    @include('partials.azari-head-assets')
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#052058">
    <title>@yield('title', 'Guest area') | Resavar</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('head')
</head>
<body class="az-user-body">
<div class="az-user-shell">
    <a class="skip-link" href="#az-user-content">Skip to main content</a>

    <aside class="az-user-sidebar" aria-label="Guest navigation">
        <div class="az-user-brand-area">
            <x-brand-logo variant="guest-sidebar" />
            <span class="az-user-sidebar-label">Guest portal</span>
        </div>

        <div class="az-user-sidebar-scroll">
            @include('user.partials.navigation')
        </div>

        <div class="az-user-support-fixed">
            @include('user.partials.support-card')
        </div>

        <div class="az-user-logout-fixed">
            <form method="POST" action="{{ route('logout') }}" class="az-user-logout-form">
                @csrf
                <button type="submit" class="az-user-logout-button">
                    <span class="material-symbols-outlined" aria-hidden="true">logout</span>
                    <span>Logout</span>
                    <span class="material-symbols-outlined" aria-hidden="true">arrow_forward</span>
                </button>
            </form>
        </div>
    </aside>

    <div class="az-user-mobile-backdrop" data-user-drawer-close aria-hidden="true"></div>

    <aside class="az-user-mobile-drawer" aria-label="Mobile guest navigation" aria-hidden="true">
        <div class="az-user-drawer-header">
            <x-brand-logo variant="guest-drawer" />
            <button class="az-user-drawer-close" type="button" data-user-drawer-close aria-label="Close navigation">
                <span class="material-symbols-outlined" aria-hidden="true">close</span>
            </button>
        </div>

        <div class="az-user-drawer-scroll">
            @include('user.partials.navigation')
        </div>

        <div class="az-user-support-fixed">
            @include('user.partials.support-card')
        </div>

        <div class="az-user-logout-fixed">
            <form method="POST" action="{{ route('logout') }}" class="az-user-logout-form">
                @csrf
                <button type="submit" class="az-user-logout-button">
                    <span class="material-symbols-outlined" aria-hidden="true">logout</span>
                    <span>Logout</span>
                    <span class="material-symbols-outlined" aria-hidden="true">arrow_forward</span>
                </button>
            </form>
        </div>
    </aside>

    <main class="az-user-main" id="az-user-content">
        <header class="az-user-topbar">
            <div class="az-user-topbar-left">
                <button class="az-user-menu-button" type="button" data-user-drawer-open aria-expanded="false" aria-label="Open guest navigation">
                    <span class="material-symbols-outlined" aria-hidden="true">menu</span>
                </button>
                <div>
                    <div class="az-user-kicker">@yield('kicker', 'Guest area')</div>
                    <h1 class="az-user-page-title">@yield('page_title', 'Your Resavar stay')</h1>
                </div>
            </div>

            <a class="az-user-profile-chip" href="{{ route('user.profile.edit') }}" aria-label="Open profile">
                <span class="az-user-avatar">
                    @if (auth()->user()->profile_photo_path)
                        <img src="{{ \Storage::url(auth()->user()->profile_photo_path) }}" alt="">
                    @else
                        {{ collect(explode(' ', auth()->user()->name))->map(fn ($name) => mb_substr($name, 0, 1))->take(2)->implode('') }}
                    @endif
                </span>
                <span class="az-user-profile-meta">
                    <strong>{{ auth()->user()->name }}</strong>
                    <span>{{ auth()->user()->currentIdentity()->exists() ? 'Identity on file' : 'Guest account' }}</span>
                </span>
            </a>
        </header>

        <div class="az-user-content">
            @foreach (['success' => 'success', 'warning' => 'warning', 'error' => 'error'] as $flashKey => $flashTone)
                @if (session($flashKey))
                    <div class="az-user-flash az-user-flash--{{ $flashTone }}" role="status">
                        <span class="material-symbols-outlined" aria-hidden="true">
                            {{ $flashTone === 'error' ? 'error' : ($flashTone === 'warning' ? 'warning' : 'check_circle') }}
                        </span>
                        <span>{{ session($flashKey) }}</span>
                    </div>
                @endif
            @endforeach

            @if ($errors->any())
                <div class="az-user-flash az-user-flash--error" role="alert">
                    <span class="material-symbols-outlined" aria-hidden="true">error</span>
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

    <nav class="az-user-mobile-bottom" aria-label="Guest quick navigation">
        <a class="{{ request()->routeIs('user.dashboard') ? 'is-active' : '' }}" href="{{ route('user.dashboard') }}">
            <span class="material-symbols-outlined" aria-hidden="true">space_dashboard</span><span>Home</span>
        </a>
        <a class="{{ request()->routeIs('user.bookings.*') ? 'is-active' : '' }}" href="{{ route('user.bookings.index') }}">
            <span class="material-symbols-outlined" aria-hidden="true">calendar_month</span><span>Bookings</span>
        </a>
        <a class="{{ request()->routeIs('user.payments.*') ? 'is-active' : '' }}" href="{{ route('user.payments.index') }}">
            <span class="material-symbols-outlined" aria-hidden="true">account_balance_wallet</span><span>Payments</span>
        </a>
        <a class="{{ request()->routeIs('user.identity.*') ? 'is-active' : '' }}" href="{{ route('user.identity.index') }}">
            <span class="material-symbols-outlined" aria-hidden="true">badge</span><span>Identity</span>
        </a>
        <a class="{{ request()->routeIs('user.profile.*') ? 'is-active' : '' }}" href="{{ route('user.profile.edit') }}">
            <span class="material-symbols-outlined" aria-hidden="true">person</span><span>Profile</span>
        </a>
    </nav>
</div>

@stack('scripts')
<x-azari-toasts />

<script>
document.addEventListener('DOMContentLoaded', function () {
    const body = document.body;
    const drawer = document.querySelector('.az-user-mobile-drawer');
    const openButton = document.querySelector('[data-user-drawer-open]');
    const closeButtons = document.querySelectorAll('[data-user-drawer-close]');

    if (!drawer || !openButton) return;

    const closeDrawer = () => {
        body.classList.remove('az-user-drawer-open');
        openButton.setAttribute('aria-expanded', 'false');
        drawer.setAttribute('aria-hidden', 'true');
    };

    const openDrawer = () => {
        body.classList.add('az-user-drawer-open');
        openButton.setAttribute('aria-expanded', 'true');
        drawer.setAttribute('aria-hidden', 'false');
        window.requestAnimationFrame(() => drawer.querySelector('.az-user-drawer-close')?.focus());
    };

    openButton.addEventListener('click', openDrawer);
    closeButtons.forEach(button => button.addEventListener('click', closeDrawer));
    document.addEventListener('keydown', event => {
        if (event.key === 'Escape') closeDrawer();
    });
    window.addEventListener('resize', () => {
        if (window.innerWidth > 980) closeDrawer();
    });
});
</script>
</body>
</html>
