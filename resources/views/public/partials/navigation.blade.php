<header class="site-header" data-site-header>
    <div class="site-container nav-shell">
        <a href="{{ route('home') }}" class="brand azari-brand azari-brand--header azari-header-logo" aria-label="{{ config('app.name', 'Resavar') }} home">

        <span class="brand-logo-slot azari-brand__logo-slot azari-header-logo__slot">
                <img
                    src="{{ asset(request()->path() === '/' ? 'images/resavar-logo-dark.png' : 'images/resavar-logo-light.png') }}?v=20261006-4"
                    data-azari-public-logo
                    data-dark-logo="{{ asset('images/resavar-logo-dark.png') }}?v=20261006-3"
                    data-light-logo="{{ asset('images/resavar-logo-light.png') }}?v=20261006-3"
                    alt="{{ config('app.name', 'Resavar') }}"
                    class="brand-image azari-brand__image azari-header-logo__image"
                    loading="eager"
                    decoding="async"
                >
            </span>

        </a>

        <nav class="desktop-navigation" aria-label="Primary navigation">
            <a href="{{ route('public.apartments') }}">{{ __('resarva.nav.apartments') }}</a>
            <div class="nav-popover" data-popover>
                <button type="button" class="nav-popover-trigger" data-popover-trigger
                        aria-expanded="false" aria-controls="rooms-popover">
                    {{ __('resarva.nav.rooms') }}
                    <span class="material-symbols-outlined" aria-hidden="true">keyboard_arrow_down</span>
                </button>

                <div class="nav-popover-panel" id="rooms-popover" data-popover-panel hidden>
                    <a href="{{ route('public.rooms') }}" class="{{ request()->routeIs('public.rooms') ? 'is-active' : '' }}">
                        <span class="material-symbols-outlined" aria-hidden="true">bed</span>
                        <span class="nav-popover-label">{{ __('resarva.nav.rooms') }}</span>
                    </a>
                    <a href="{{ route('availability.index') }}" class="{{ request()->routeIs('availability.*') ? 'is-active' : '' }}">
                        <span class="material-symbols-outlined" aria-hidden="true">event_available</span>
                        <span class="nav-popover-label">{{ __('resarva.nav.availability') }}</span>
                    </a>
                </div>
            </div>

            @if(Route::has('public.list-property'))
                <a href="{{ route('public.list-property') }}">List your property</a>
            @endif

            <a href="{{ route('public.about') }}">{{ __('resarva.nav.about') }}</a>

            <a href="{{ route('public.contact') }}">{{ __('resarva.nav.contact') }}</a>

        </nav>


        <div class="nav-actions">
            <form action="{{ route('language.update') }}" method="POST" class="resavar-language-picker">
                @csrf
                <label for="resavar-language" class="sr-only">Language</label>
                <select id="resavar-language" name="locale" aria-label="Language"
                        onchange="this.form.requestSubmit()">
                    @foreach(config('localization.supported_locales', []) as $code => $name)
                        <option value="{{ $code }}" @selected(app()->getLocale() === $code)>{{ $name }}</option>
                    @endforeach
                </select>
                <noscript><button type="submit">Change language</button></noscript>
            </form>
            <a href="{{ route('bookings.verify') }}" class="nav-text-action">{{ __('resarva.nav.verify_booking') }}</a>

            @auth
                <a href="{{ auth()->user()->isStaff() ? route('azari.admin.dashboard') : route('user.dashboard') }}" class="nav-text-action">{{ __('resarva.nav.dashboard') }}</a>
            @else
                <a href="{{ route('login') }}" class="nav-text-action">{{ __('resarva.nav.login') }}</a>
            @endauth

            <a href="{{ route('public.book-now') }}" class="button button-brass">{{ __('resarva.nav.book_now') }}</a>

            <button
                type="button"
                class="mobile-menu-button azari-menu-toggle"
                data-drawer-open="mobile-navigation"
                data-azari-menu-toggle
                aria-label="Open navigation"
                aria-expanded="false"
            >
                <span class="azari-menu-toggle__icon" aria-hidden="true">
                    <span class="azari-menu-toggle__line azari-menu-toggle__line--top"></span>
                    <span class="azari-menu-toggle__line azari-menu-toggle__line--middle"></span>
                    <span class="azari-menu-toggle__line azari-menu-toggle__line--bottom"></span>
                </span>
            </button>
        </div>
    </div>
</header>
